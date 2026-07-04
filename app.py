"""
app.py — TrustFundBD (Python / Flask edition)
================================================

This is a plain Flask + SQLite rewrite of the original TrustFundBD
crowdfunding platform (previously a Next.js + Supabase app). Same idea,
same flow, same trust rules — just simpler, so it's easy to read top to
bottom and easy to extend with blockchain features later.

THE FLOW (unchanged from the original app):
  1. Anyone can register as a donor, a campaign creator, or an admin
     (the "Admin (Demo)" option on the register page is intentional —
     it mirrors the original demo app so you can test admin features).
  2. A creator starts a campaign -> it sits as "pending".
  3. An admin reviews and approves it -> it goes "active" and can
     receive donations.
  4. A donor picks an amount + payment method, sends money manually
     (bKash/Nagad/bank), then submits the transaction ID -> the
     donation sits as "pending".
  5. An admin manually verifies the transaction ID and approves it ->
     the campaign's collected_amount goes up and its trust score is
     recalculated.
  6. The creator uploads proof (receipts, photos) of how the money is
     being used -> admin approves -> trust score goes up again.
  7. Once there's at least one approved proof, the creator can request
     a withdrawal (up to collected - released) -> admin approves ->
     released_amount goes up. This is the "funds leave escrow" moment.

WHERE BLOCKCHAIN PLUGS IN:
  Every step above that "establishes trust" (donation verified, proof
  verified, funds released) calls `ledger.record_event()`. Today that
  just appends to a local JSON file and returns a fake transaction
  hash. Swap that one function out for a real Web3 call later and the
  rest of the app doesn't need to change — see ledger.py.
"""

import os
import json
import time
import secrets
from datetime import datetime
from functools import wraps

from flask import (
    Flask, render_template, request, redirect, url_for, session, flash, g, abort,
    send_from_directory
)
from markupsafe import Markup
from werkzeug.utils import secure_filename
from sqlalchemy.exc import IntegrityError
from sqlalchemy.pool import NullPool
import requests

from models import db, User, Campaign, Donation, Proof, CampaignEdit, WithdrawRequest
from trust import recalculate_trust_score
from ledger import record_event


# ---------------------------------------------------------------------------
# APP SETUP
# ---------------------------------------------------------------------------
BASE_DIR = os.path.dirname(os.path.abspath(__file__))

app = Flask(__name__)

# SECRET_KEY signs the session cookie (which is what keeps you logged in).
# Reads from the SECRET_KEY environment variable if you set one; otherwise
# falls back to a fixed dev value so the app still runs out of the box.
# Before putting this online for real, set a real SECRET_KEY env var —
# anyone who knows this value could forge a login session.
app.config["SECRET_KEY"] = os.environ.get("SECRET_KEY", "dev-secret-change-me-before-deploying")

# DATABASE: uses Supabase Postgres when a DATABASE_URL env var is set (the
# Vercel deployment); otherwise falls back to a local SQLite file, so the
# app still runs instantly with zero setup for local development.
#
# On Vercel, DATABASE_URL should be Supabase's "Transaction pooler" connection
# string (port 6543), not the direct database connection (port 5432).
# Serverless platforms spin up many short-lived processes at once, and the
# pooler is built to handle that; the direct connection has a small, fixed
# limit that would get exhausted almost immediately under real traffic.
DATABASE_URL = os.environ.get("DATABASE_URL")
if DATABASE_URL:
    app.config["SQLALCHEMY_DATABASE_URI"] = DATABASE_URL
    app.config["SQLALCHEMY_ENGINE_OPTIONS"] = {
        # NullPool: don't keep a local connection pool inside each serverless
        # instance — let Supabase's own pooler (pgbouncer) do that job.
        "poolclass": NullPool,
        "pool_pre_ping": True,
    }
else:
    app.config["SQLALCHEMY_DATABASE_URI"] = "sqlite:///" + os.path.join(BASE_DIR, "trustfundbd.db")

app.config["SQLALCHEMY_TRACK_MODIFICATIONS"] = False
app.config["UPLOAD_FOLDER"] = os.path.join(BASE_DIR, "static", "uploads")
app.config["MAX_CONTENT_LENGTH"] = 10 * 1024 * 1024  # 10 MB upload limit

# FILE STORAGE: same idea as the database above. When running against
# Supabase (DATABASE_URL set), campaign images and proof files go to a
# public Supabase Storage bucket instead of local disk, since Vercel's
# filesystem doesn't persist between requests. SUPABASE_URL/SUPABASE_KEY
# are the project's public API URL and publishable/anon key — safe values
# to put in a serverless environment, not secret admin credentials.
SUPABASE_URL = os.environ.get("SUPABASE_URL")
SUPABASE_KEY = os.environ.get("SUPABASE_KEY")
SUPABASE_UPLOAD_BUCKET = "trustfundbd-uploads"

# Session cookie hardening: JS can't read the cookie (HTTPONLY), and it
# won't be sent along with cross-site requests (SAMESITE=Lax) — a basic,
# free defense against session theft and some CSRF attacks.
app.config["SESSION_COOKIE_HTTPONLY"] = True
app.config["SESSION_COOKIE_SAMESITE"] = "Lax"

db.init_app(app)

ALLOWED_EXTENSIONS = {"png", "jpg", "jpeg", "gif", "webp", "pdf"}

CATEGORIES = [
    "Medical", "Education", "Disaster Relief", "Community",
    "Environment", "Children", "Elderly Care", "Other",
]

PAYMENT_LABELS = {"bkash": "bKash", "nagad": "Nagad", "bank": "Bank Transfer"}
PAYMENT_NUMBERS = {"bkash": "01XXX-XXXXXX", "nagad": "01YYY-YYYYYY", "bank": "A/C: XXXX-XXXX-XXXX"}
QUICK_AMOUNTS = [500, 1000, 2500, 5000]


# ---------------------------------------------------------------------------
# SMALL HELPERS (auth, uploads, formatting)
# ---------------------------------------------------------------------------
@app.before_request
def load_current_user():
    """Runs before every request. Puts the logged-in user on `g.user` so
    every route and every template can just read g.user."""
    g.user = None
    user_id = session.get("user_id")
    if user_id:
        g.user = db.session.get(User, user_id)


def login_required(view):
    """Decorator: route requires ANY logged-in user."""
    @wraps(view)
    def wrapped(*args, **kwargs):
        if not g.user:
            flash("Please sign in to continue.", "error")
            return redirect(url_for("login", next=request.path))
        return view(*args, **kwargs)
    return wrapped


def role_required(*roles):
    """Decorator: route requires a logged-in user with one of these roles."""
    def decorator(view):
        @wraps(view)
        def wrapped(*args, **kwargs):
            if not g.user:
                flash("Please sign in to continue.", "error")
                return redirect(url_for("login", next=request.path))
            if g.user.role not in roles:
                flash("You don't have access to that page.", "error")
                return redirect(url_for("home"))
            return view(*args, **kwargs)
        return wrapped
    return decorator


def safe_int(value, default=0):
    """Turns request input into an int without ever raising — bad or
    missing input (None, "", "abc") just falls back to `default` instead
    of crashing the request with a 500 error."""
    try:
        return int(value)
    except (TypeError, ValueError):
        return default


# ---------------------------------------------------------------------------
# CSRF PROTECTION
# ---------------------------------------------------------------------------
# Every POST form in this app (login, donate, admin approve/reject buttons,
# withdrawals, etc.) is a place someone's browser sends a state-changing
# request. Without a CSRF token, a malicious site could trick a logged-in
# admin's browser into submitting one of these forms invisibly. The fix
# here is simple on purpose: one random token per session, stored server
# side in the signed session cookie, echoed back as a hidden field in
# every form, and checked on every POST before the route runs.
def get_csrf_token():
    if "csrf_token" not in session:
        session["csrf_token"] = secrets.token_hex(16)
    return session["csrf_token"]


def csrf_field():
    """Used in templates as {{ csrf_field() }} inside every <form method="post">."""
    return Markup(f'<input type="hidden" name="csrf_token" value="{get_csrf_token()}">')


app.jinja_env.globals["csrf_token"] = get_csrf_token
app.jinja_env.globals["csrf_field"] = csrf_field


@app.before_request
def csrf_protect():
    if request.method == "POST":
        sent_token = request.form.get("csrf_token", "")
        real_token = session.get("csrf_token", "")
        if not real_token or not secrets.compare_digest(sent_token, real_token):
            abort(400, description="Your session expired or this form was submitted from an untrusted page.")


@app.errorhandler(400)
def handle_bad_request(e):
    flash("That request looked invalid or your session expired — please try again.", "error")
    return redirect(request.referrer or url_for("home"))


def allowed_file(filename):
    return "." in filename and filename.rsplit(".", 1)[1].lower() in ALLOWED_EXTENSIONS


def save_upload(file_storage, subfolder):
    """Saves an uploaded file and returns its public URL, or None if
    nothing valid was uploaded.

    Two modes, picked automatically based on DATABASE_URL:
      - Supabase/Vercel (DATABASE_URL set): uploads to the shared
        "trustfundbd-uploads" Storage bucket over Supabase's HTTP API,
        since Vercel's serverless functions have no persistent local disk.
      - Local development (no DATABASE_URL): saves straight to
        static/uploads/<subfolder>/ on disk, same as always — zero setup.
    """
    if not file_storage or file_storage.filename == "":
        return None
    if not allowed_file(file_storage.filename):
        return None

    filename = secure_filename(file_storage.filename)
    unique_name = f"{int(time.time() * 1000)}_{filename}"

    if DATABASE_URL:
        return _upload_to_supabase_storage(file_storage, subfolder, unique_name)

    folder = os.path.join(app.config["UPLOAD_FOLDER"], subfolder)
    os.makedirs(folder, exist_ok=True)
    file_storage.save(os.path.join(folder, unique_name))
    return f"/static/uploads/{subfolder}/{unique_name}"


def _upload_to_supabase_storage(file_storage, subfolder, unique_name):
    """Uploads one file to the public "trustfundbd-uploads" Supabase
    Storage bucket and returns its public URL. Only used when DATABASE_URL
    is set — i.e. the app is running against Supabase, not local SQLite."""
    if not SUPABASE_URL or not SUPABASE_KEY:
        # Misconfigured deployment (env vars not set) — skip the image
        # rather than crashing the whole request over an upload.
        return None

    object_path = f"{subfolder}/{unique_name}"
    content_type = file_storage.mimetype or "application/octet-stream"

    try:
        response = requests.post(
            f"{SUPABASE_URL}/storage/v1/object/{SUPABASE_UPLOAD_BUCKET}/{object_path}",
            headers={
                "Authorization": f"Bearer {SUPABASE_KEY}",
                "apikey": SUPABASE_KEY,
                "Content-Type": content_type,
            },
            data=file_storage.read(),
            timeout=15,
        )
    except requests.RequestException:
        return None

    if response.status_code not in (200, 201):
        return None

    return f"{SUPABASE_URL}/storage/v1/object/public/{SUPABASE_UPLOAD_BUCKET}/{object_path}"


def format_amount(value):
    value = value or 0
    if float(value).is_integer():
        return f"৳{int(value):,}"
    return f"৳{value:,.2f}"


def format_date(dt):
    if not dt:
        return ""
    return dt.strftime("%d %b %Y")


def time_ago(dt):
    if not dt:
        return ""
    diff = datetime.utcnow() - dt
    minutes = int(diff.total_seconds() // 60)
    if minutes < 1:
        return "just now"
    if minutes < 60:
        return f"{minutes}m ago"
    hours = minutes // 60
    if hours < 24:
        return f"{hours}h ago"
    days = hours // 24
    if days < 30:
        return f"{days}d ago"
    return format_date(dt)


# Make these available inside every Jinja template as filters, e.g. {{ x|amount }}
app.jinja_env.filters["amount"] = format_amount
app.jinja_env.filters["dateonly"] = format_date
app.jinja_env.filters["timeago"] = time_ago


@app.context_processor
def inject_globals():
    """Variables available in every template without passing them manually."""
    return {
        "CATEGORIES": CATEGORIES,
        "PAYMENT_LABELS": PAYMENT_LABELS,
        "PAYMENT_NUMBERS": PAYMENT_NUMBERS,
        "QUICK_AMOUNTS": QUICK_AMOUNTS,
    }


# ---------------------------------------------------------------------------
# AUTH ROUTES
# ---------------------------------------------------------------------------
@app.route("/register", methods=["GET", "POST"])
def register():
    if g.user:
        return redirect(url_for("home"))

    if request.method == "POST":
        name = request.form.get("name", "").strip()
        email = request.form.get("email", "").strip().lower()
        password = request.form.get("password", "")
        role = request.form.get("role", "donor")
        if role not in ("donor", "creator", "admin"):
            role = "donor"

        # A very small sanity check on the email shape — this is not meant
        # to be a full RFC validator, just enough to catch obvious typos.
        email_looks_valid = "@" in email and "." in email.split("@")[-1] and " " not in email

        if len(name) < 2 or not email_looks_valid or len(password) < 6:
            flash("Please enter a valid name, email, and a password of at least 6 characters.", "error")
            return render_template("register.html", form=request.form)

        if User.query.filter_by(email=email).first():
            flash("An account with that email already exists.", "error")
            return render_template("register.html", form=request.form)

        user = User(name=name, email=email, role=role)
        user.set_password(password)
        db.session.add(user)
        try:
            db.session.commit()
        except IntegrityError:
            # Two people submitted the same email at almost the same instant
            # and both passed the check above — the database's own unique
            # constraint is the real guard here. Fail politely instead of
            # crashing with a 500 error.
            db.session.rollback()
            flash("An account with that email already exists.", "error")
            return render_template("register.html", form=request.form)

        session.clear()
        session["user_id"] = user.id
        flash(f"Welcome to TrustFundBD, {user.name}!", "success")
        return redirect(url_for("post_login_redirect"))

    return render_template("register.html", form={})


def _safe_next_url(candidate):
    """Only ever redirect to a path inside this same app after login.
    Without this check, a link like /login?next=https://evil.example.com
    could send a freshly-logged-in user straight to a phishing site —
    this is the classic "open redirect" bug."""
    if candidate and candidate.startswith("/") and not candidate.startswith("//"):
        return candidate
    return None


@app.route("/login", methods=["GET", "POST"])
def login():
    if g.user:
        return redirect(url_for("home"))

    next_url = request.values.get("next", "")

    if request.method == "POST":
        email = request.form.get("email", "").strip().lower()
        password = request.form.get("password", "")
        user = User.query.filter_by(email=email).first()

        if not user or not user.check_password(password):
            # Same message either way (unknown email vs. wrong password) so
            # a would-be attacker can't use this form to check which emails
            # have accounts on the site.
            flash("Invalid email or password.", "error")
            return render_template("login.html", next=next_url)

        session.clear()
        session["user_id"] = user.id
        flash(f"Welcome back, {user.name}!", "success")
        safe_next = _safe_next_url(next_url)
        return redirect(safe_next) if safe_next else redirect(url_for("post_login_redirect"))

    return render_template("login.html", next=next_url)


@app.route("/logout")
def logout():
    session.clear()
    flash("You've been signed out.", "success")
    return redirect(url_for("home"))


@app.route("/_post_login")
def post_login_redirect():
    """Sends a freshly logged-in user to the right home screen for their role."""
    if not g.user:
        return redirect(url_for("login"))
    if g.user.role == "admin":
        return redirect(url_for("admin_dashboard"))
    if g.user.role == "creator":
        return redirect(url_for("creator_dashboard"))
    return redirect(url_for("donor_dashboard"))


# ---------------------------------------------------------------------------
# PROJECT GUIDES — the HTML documentation files written alongside this app
# (architecture, blockchain integration, Bangla versions). Served straight
# from disk so anyone with the link can read them; they're static reference
# docs with no user data, so no login is required.
# ---------------------------------------------------------------------------
GUIDE_FILES = {
    "architecture": "ARCHITECTURE_GUIDE.html",
    "blockchain": "BLOCKCHAIN_INTEGRATION_GUIDE.html",
    "project-bangla": "PROJECT_GUIDE_BANGLA.html",
    "blockchain-bangla": "BLOCKCHAIN_GUIDE_BANGLA.html",
}


@app.route("/guides")
def guides_index():
    return render_template("guides_index.html", guides=GUIDE_FILES)


@app.route("/guides/<slug>")
def guide_detail(slug):
    filename = GUIDE_FILES.get(slug)
    if not filename:
        abort(404)
    return send_from_directory(BASE_DIR, filename)


# ---------------------------------------------------------------------------
# PUBLIC ROUTES — home page & campaign browsing
# ---------------------------------------------------------------------------
@app.route("/")
def home():
    featured = (
        Campaign.query.filter_by(status="active")
        .order_by(Campaign.created_at.desc())
        .limit(6)
        .all()
    )
    return render_template("home.html", campaigns=featured)


@app.route("/campaigns")
def campaigns_list():
    category = request.args.get("category", "")
    search = request.args.get("search", "").strip()
    min_trust = safe_int(request.args.get("min_trust"), 0)
    verified_only = request.args.get("verified_only") == "1"

    query = Campaign.query.filter_by(status="active")
    if category:
        query = query.filter_by(category=category)
    if min_trust:
        query = query.filter(Campaign.trust_score >= min_trust)

    campaigns = query.order_by(Campaign.created_at.desc()).all()

    if search:
        s = search.lower()
        campaigns = [c for c in campaigns if s in c.title.lower() or s in c.description.lower()]
    if verified_only:
        campaigns = [c for c in campaigns if c.creator.verified]

    return render_template(
        "campaigns.html",
        campaigns=campaigns,
        category=category,
        search=search,
        min_trust=min_trust,
        verified_only=verified_only,
    )


@app.route("/campaigns/<int:campaign_id>")
def campaign_detail(campaign_id):
    campaign = Campaign.query.get_or_404(campaign_id)
    proofs = campaign.approved_proof_list()
    donations = sorted(
        [d for d in campaign.donations if d.status == "approved"],
        key=lambda d: d.created_at, reverse=True,
    )[:5]
    edits = [e for e in campaign.edits if e.status == "approved"]
    return render_template(
        "campaign_detail.html", campaign=campaign, proofs=proofs, donations=donations, edits=edits,
    )


# ---------------------------------------------------------------------------
# DONOR ROUTES
# ---------------------------------------------------------------------------
@app.route("/campaigns/<int:campaign_id>/donate", methods=["POST"])
@login_required
def donate(campaign_id):
    campaign = Campaign.query.get_or_404(campaign_id)
    if campaign.status != "active":
        flash("This campaign is not accepting donations right now.", "error")
        return redirect(url_for("campaign_detail", campaign_id=campaign_id))

    try:
        amount = float(request.form.get("amount", 0))
    except ValueError:
        amount = 0
    method = request.form.get("payment_method", "bkash")
    tx_id = request.form.get("transaction_id", "").strip()
    message = request.form.get("message", "").strip()[:200]

    if amount < 10 or method not in PAYMENT_LABELS or not tx_id:
        flash("Please enter a valid amount (min ৳10) and your transaction ID.", "error")
        return redirect(url_for("campaign_detail", campaign_id=campaign_id))

    donation = Donation(
        campaign_id=campaign.id, donor_id=g.user.id, amount=amount,
        payment_method=method, transaction_id=tx_id, message=message or None,
        status="pending",
    )
    db.session.add(donation)
    db.session.commit()

    flash("Donation submitted! Our admin team will verify your transaction within 24 hours.", "success")
    return redirect(url_for("campaign_detail", campaign_id=campaign_id))


@app.route("/dashboard")
@login_required
def donor_dashboard():
    my_donations = (
        Donation.query.filter_by(donor_id=g.user.id)
        .order_by(Donation.created_at.desc())
        .all()
    )
    return render_template("dashboard.html", donations=my_donations)


# ---------------------------------------------------------------------------
# CREATOR ROUTES
# ---------------------------------------------------------------------------
@app.route("/dashboard/creator")
@role_required("creator", "admin")
def creator_dashboard():
    campaigns = (
        Campaign.query.filter_by(creator_id=g.user.id)
        .order_by(Campaign.created_at.desc())
        .all()
    )
    total_raised = sum(c.collected_amount for c in campaigns)
    active_count = len([c for c in campaigns if c.status == "active"])
    return render_template(
        "creator_dashboard.html", campaigns=campaigns,
        total_raised=total_raised, active_count=active_count,
    )


@app.route("/dashboard/create", methods=["GET", "POST"])
@role_required("creator", "admin")
def create_campaign():
    if request.method == "POST":
        title = request.form.get("title", "").strip()
        description = request.form.get("description", "").strip()
        category = request.form.get("category", "General")
        try:
            goal_amount = float(request.form.get("goal_amount", 0))
        except ValueError:
            goal_amount = 0

        if len(title) < 5 or len(description) < 20 or goal_amount <= 0:
            flash("Please provide a title (5+ characters), a description (20+ characters), and a goal amount.", "error")
            return render_template("create_campaign.html", form=request.form)

        image_url = save_upload(request.files.get("image"), "campaigns")
        additional_images = []
        for f in request.files.getlist("additional_images")[:4]:
            saved = save_upload(f, "campaigns")
            if saved:
                additional_images.append(saved)

        campaign = Campaign(
            title=title,
            description=description,
            category=category,
            goal_amount=goal_amount,
            image_url=image_url,
            additional_images_json=json.dumps(additional_images),
            contact_phone=request.form.get("contact_phone", "").strip() or None,
            contact_email=request.form.get("contact_email", "").strip() or g.user.email,
            contact_facebook=request.form.get("contact_facebook", "").strip() or None,
            contact_whatsapp=request.form.get("contact_whatsapp", "").strip() or None,
            status="pending",
            creator_id=g.user.id,
        )
        db.session.add(campaign)
        db.session.commit()

        flash("Campaign submitted! It will go live once our admin team reviews it.", "success")
        return redirect(url_for("creator_proofs", campaign=campaign.id, new=1))

    return render_template("create_campaign.html", form={})


@app.route("/dashboard/creator/edit/<int:campaign_id>", methods=["GET", "POST"])
@role_required("creator", "admin")
def edit_campaign(campaign_id):
    campaign = Campaign.query.get_or_404(campaign_id)
    if campaign.creator_id != g.user.id:
        abort(403)

    if request.method == "POST":
        new_description = request.form.get("description", "").strip()
        if len(new_description) < 20:
            flash("Description must be at least 20 characters.", "error")
        else:
            edit = CampaignEdit(
                campaign_id=campaign.id,
                old_description=campaign.description,
                new_description=new_description,
                status="pending",
            )
            db.session.add(edit)
            db.session.commit()
            flash("Edit submitted for admin review. It will replace the live description once approved.", "success")
            return redirect(url_for("creator_dashboard"))

    return render_template("edit_campaign.html", campaign=campaign)


@app.route("/dashboard/creator/proofs", methods=["GET", "POST"])
@role_required("creator", "admin")
def creator_proofs():
    campaigns = (
        Campaign.query.filter_by(creator_id=g.user.id)
        .order_by(Campaign.created_at.desc())
        .all()
    )
    campaign_ids = [c.id for c in campaigns]

    if request.method == "POST":
        campaign_id = safe_int(request.form.get("campaign_id"))
        title = request.form.get("title", "").strip()
        description = request.form.get("description", "").strip()

        if campaign_id not in campaign_ids:
            flash("Please select one of your campaigns.", "error")
        elif not title or not description:
            flash("Please provide a proof title and description.", "error")
        else:
            file_url = save_upload(request.files.get("proof_file"), "proofs")
            proof = Proof(
                campaign_id=campaign_id, title=title, description=description,
                file_url=file_url, status="pending",
            )
            db.session.add(proof)
            db.session.commit()
            flash("Proof submitted! Our admin team will review it shortly.", "success")
            return redirect(url_for("creator_proofs"))

    proofs = (
        Proof.query.filter(Proof.campaign_id.in_(campaign_ids))
        .order_by(Proof.created_at.desc())
        .all()
        if campaign_ids else []
    )
    return render_template(
        "creator_proofs.html", campaigns=campaigns, proofs=proofs,
        preselected=request.args.get("campaign", ""),
        is_new=request.args.get("new") == "1",
    )


@app.route("/dashboard/creator/withdraw", methods=["GET", "POST"])
@role_required("creator", "admin")
def creator_withdraw():
    campaigns = Campaign.query.filter_by(creator_id=g.user.id, status="active").all()
    campaign_ids = [c.id for c in campaigns]
    withdrawals = (
        WithdrawRequest.query.filter(WithdrawRequest.campaign_id.in_(campaign_ids))
        .order_by(WithdrawRequest.created_at.desc())
        .all()
        if campaign_ids else []
    )

    if request.method == "POST":
        campaign_id = safe_int(request.form.get("campaign_id"))
        campaign = next((c for c in campaigns if c.id == campaign_id), None)
        proof_id = safe_int(request.form.get("proof_id"))
        try:
            amount = float(request.form.get("requested_amount", 0))
        except ValueError:
            amount = 0

        if not campaign:
            flash("Please select one of your active campaigns.", "error")
        else:
            approved_proofs = [p for p in campaign.proofs if p.status == "approved"]
            valid_proof = next((p for p in approved_proofs if p.id == proof_id), None)
            max_withdraw = campaign.remaining_in_escrow()

            if not approved_proofs:
                flash("You need at least one approved proof before requesting a withdrawal.", "error")
            elif not valid_proof:
                flash("Please select a valid approved proof for this campaign.", "error")
            elif amount <= 0 or amount > max_withdraw:
                flash(f"Amount must be between 1 and {format_amount(max_withdraw)} (what's available in escrow).", "error")
            else:
                wr = WithdrawRequest(
                    campaign_id=campaign.id, requested_amount=amount,
                    proof_id=proof_id, status="pending",
                )
                db.session.add(wr)
                db.session.commit()
                flash("Withdrawal request submitted! Admin will review it soon.", "success")
                return redirect(url_for("creator_withdraw"))

    # Built as plain dicts (not model objects) so it can drop straight into
    # a <script> tag as JSON for the campaign-select JavaScript on the page.
    # Escaping "<" as < stops a proof title like "</script><script>..."
    # from breaking out of the <script> tag and running as real JavaScript.
    campaign_proofs_json = json.dumps({
        c.id: [{"id": p.id, "title": p.title} for p in c.proofs if p.status == "approved"]
        for c in campaigns
    }).replace("<", "\\u003c")
    return render_template(
        "creator_withdraw.html", campaigns=campaigns, withdrawals=withdrawals,
        campaign_proofs_json=campaign_proofs_json,
    )


# ---------------------------------------------------------------------------
# ADMIN ROUTES — every approval below is a "trust" moment.
# ---------------------------------------------------------------------------
@app.route("/admin")
@role_required("admin")
def admin_dashboard():
    all_campaigns = Campaign.query.all()
    return render_template(
        "admin_dashboard.html",
        pending_campaigns=Campaign.query.filter_by(status="pending").count(),
        pending_donations=Donation.query.filter_by(status="pending").count(),
        pending_proofs=Proof.query.filter_by(status="pending").count(),
        pending_withdrawals=WithdrawRequest.query.filter_by(status="pending").count(),
        total_collected=sum(c.collected_amount for c in all_campaigns),
        total_released=sum(c.released_amount for c in all_campaigns),
    )


@app.route("/admin/campaigns", methods=["GET", "POST"])
@role_required("admin")
def admin_campaigns():
    if request.method == "POST":
        campaign = Campaign.query.get_or_404(safe_int(request.form.get("campaign_id")))
        action = request.form.get("action")
        if action == "approve":
            campaign.status = "active"
            flash(f'"{campaign.title}" approved and is now live.', "success")
        elif action == "reject":
            campaign.status = "rejected"
            flash(f'"{campaign.title}" rejected.', "success")
        db.session.commit()
        return redirect(url_for("admin_campaigns", filter=request.args.get("filter", "pending")))

    status_filter = request.args.get("filter", "pending")
    query = Campaign.query if status_filter == "all" else Campaign.query.filter_by(status=status_filter)
    campaigns = query.order_by(Campaign.created_at.desc()).all()
    return render_template("admin_campaigns.html", campaigns=campaigns, status_filter=status_filter)


@app.route("/admin/donations", methods=["GET", "POST"])
@role_required("admin")
def admin_donations():
    if request.method == "POST":
        donation = Donation.query.get_or_404(safe_int(request.form.get("donation_id")))
        action = request.form.get("action")
        campaign = donation.campaign

        if action == "approve" and donation.status == "pending":
            donation.status = "approved"
            campaign.collected_amount += donation.amount
            campaign.last_update_at = datetime.utcnow()
            recalculate_trust_score(campaign)

            # ----------------------------------------------------------
            # BLOCKCHAIN HOOK: a donation just became verified money.
            # This is exactly the kind of event donors want to see
            # permanently, publicly recorded — perfect for on-chain.
            # ----------------------------------------------------------
            donation.tx_hash = record_event("donation_approved", {
                "donation_id": donation.id,
                "campaign_id": campaign.id,
                "amount": donation.amount,
                "donor_id": donation.donor_id,
            })

            flash("Donation approved and added to the campaign total.", "success")
        elif action == "reject" and donation.status == "pending":
            donation.status = "rejected"
            donation.admin_note = "Transaction could not be verified."
            flash("Donation rejected.", "success")

        db.session.commit()
        return redirect(url_for("admin_donations", filter=request.args.get("filter", "pending")))

    status_filter = request.args.get("filter", "pending")
    query = Donation.query if status_filter == "all" else Donation.query.filter_by(status=status_filter)
    donations = query.order_by(Donation.created_at.desc()).all()
    return render_template("admin_donations.html", donations=donations, status_filter=status_filter)


@app.route("/admin/proofs", methods=["GET", "POST"])
@role_required("admin")
def admin_proofs():
    if request.method == "POST":
        proof = Proof.query.get_or_404(safe_int(request.form.get("proof_id")))
        action = request.form.get("action")
        campaign = proof.campaign

        if action == "approve" and proof.status == "pending":
            proof.status = "approved"
            campaign.approved_proofs += 1
            campaign.last_update_at = datetime.utcnow()
            recalculate_trust_score(campaign)

            # BLOCKCHAIN HOOK: proof of fund usage just got verified.
            proof.tx_hash = record_event("proof_approved", {
                "proof_id": proof.id, "campaign_id": campaign.id, "title": proof.title,
            })

            flash("Proof approved.", "success")
        elif action == "reject" and proof.status == "pending":
            proof.status = "rejected"
            flash("Proof rejected.", "success")

        db.session.commit()
        return redirect(url_for("admin_proofs", filter=request.args.get("filter", "pending")))

    status_filter = request.args.get("filter", "pending")
    query = Proof.query if status_filter == "all" else Proof.query.filter_by(status=status_filter)
    proofs = query.order_by(Proof.created_at.desc()).all()
    return render_template("admin_proofs.html", proofs=proofs, status_filter=status_filter)


@app.route("/admin/withdrawals", methods=["GET", "POST"])
@role_required("admin")
def admin_withdrawals():
    if request.method == "POST":
        wr = WithdrawRequest.query.get_or_404(safe_int(request.form.get("withdrawal_id")))
        action = request.form.get("action")
        campaign = wr.campaign

        if action == "approve" and wr.status == "pending":
            wr.status = "approved"
            campaign.released_amount += wr.requested_amount

            # ------------------------------------------------------------
            # BLOCKCHAIN HOOK: this is the fund-RELEASE moment — money
            # leaving fiduciary escrow. Of everything in this app, this
            # is the single most valuable event to put on a real chain,
            # since it's the core trust promise TrustFundBD makes.
            # ------------------------------------------------------------
            wr.tx_hash = record_event("withdrawal_approved", {
                "withdrawal_id": wr.id, "campaign_id": campaign.id,
                "amount": wr.requested_amount, "proof_id": wr.proof_id,
            })

            flash("Withdrawal approved. Funds released to the creator.", "success")
        elif action == "reject" and wr.status == "pending":
            wr.status = "rejected"
            flash("Withdrawal rejected.", "success")

        db.session.commit()
        return redirect(url_for("admin_withdrawals", filter=request.args.get("filter", "pending")))

    status_filter = request.args.get("filter", "pending")
    query = WithdrawRequest.query if status_filter == "all" else WithdrawRequest.query.filter_by(status=status_filter)
    withdrawals = query.order_by(WithdrawRequest.created_at.desc()).all()
    return render_template("admin_withdrawals.html", withdrawals=withdrawals, status_filter=status_filter)


@app.route("/admin/edits", methods=["GET", "POST"])
@role_required("admin")
def admin_edits():
    if request.method == "POST":
        edit = CampaignEdit.query.get_or_404(safe_int(request.form.get("edit_id")))
        action = request.form.get("action")

        if action == "approve" and edit.status == "pending":
            edit.status = "approved"
            edit.reviewed_at = datetime.utcnow()
            edit.campaign.description = edit.new_description
            edit.campaign.last_update_at = datetime.utcnow()
            flash("Edit approved — the live description has been updated.", "success")
        elif action == "reject" and edit.status == "pending":
            edit.status = "rejected"
            edit.reviewed_at = datetime.utcnow()
            flash("Edit rejected.", "success")

        db.session.commit()
        return redirect(url_for("admin_edits", filter=request.args.get("filter", "pending")))

    status_filter = request.args.get("filter", "pending")
    query = CampaignEdit.query if status_filter == "all" else CampaignEdit.query.filter_by(status=status_filter)
    edits = query.order_by(CampaignEdit.created_at.desc()).all()
    return render_template("admin_edits.html", edits=edits, status_filter=status_filter)


@app.route("/admin/creators", methods=["GET", "POST"])
@role_required("admin")
def admin_creators():
    """Verify or unverify a campaign creator's identity.

    This was a genuine gap found while reviewing the codebase: the trust
    score formula in trust.py awards +25 points for "verified creator",
    and the campaign browse page has a "verified only" filter — but
    nothing anywhere ever set `User.verified = True`. Both features were
    silently dead. This page is the missing piece that lets an admin
    actually flip that flag.
    """
    if request.method == "POST":
        creator = User.query.get_or_404(safe_int(request.form.get("user_id")))
        if creator.role != "creator":
            abort(400)
        action = request.form.get("action")
        if action == "verify":
            creator.verified = True
            flash(f"{creator.name} is now a verified creator.", "success")
        elif action == "unverify":
            creator.verified = False
            flash(f"{creator.name}'s verification was removed.", "success")
        db.session.commit()

        # Verifying/unverifying a creator changes their trust-score inputs
        # for every campaign they run, so recalculate all of them now
        # instead of waiting for the next unrelated approval to do it.
        for campaign in creator.campaigns:
            recalculate_trust_score(campaign)
        db.session.commit()

        return redirect(url_for("admin_creators"))

    creators = User.query.filter_by(role="creator").order_by(User.created_at.desc()).all()
    return render_template("admin_creators.html", creators=creators)


# ---------------------------------------------------------------------------
# ENTRY POINT
# ---------------------------------------------------------------------------
if __name__ == "__main__":
    with app.app_context():
        db.create_all()  # creates trustfundbd.db + tables the first time you run this
    app.run(debug=True, host="127.0.0.1", port=5000)
