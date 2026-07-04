"""
models.py — Database tables for TrustFundBD
=============================================

This is a plain Python / SQLAlchemy version of the original Supabase
Postgres schema (see supabase/schema.sql in the Next.js project). Same
tables, same fields, same statuses — just running on a single local
SQLite file (trustfundbd.db) instead of Supabase, so the whole app is
easy to run and easy to read.

Every table that represents a "trust event" (a donation being verified,
a proof being approved, funds being released) has a `tx_hash` column.
It stays empty for now. Once you wire up real blockchain code (see
ledger.py), each approval will write a transaction hash into that
column — giving every donor a permanent, checkable receipt.
"""

from datetime import datetime
from werkzeug.security import generate_password_hash, check_password_hash
from flask_sqlalchemy import SQLAlchemy
import json

db = SQLAlchemy()


# ---------------------------------------------------------------------------
# USERS — donors, campaign creators, and admins all live in one table,
# distinguished by the `role` column (exactly like the original app).
# ---------------------------------------------------------------------------
class User(db.Model):
    __tablename__ = "users"

    id = db.Column(db.Integer, primary_key=True)
    name = db.Column(db.String(120), nullable=False)
    email = db.Column(db.String(120), unique=True, nullable=False)
    password_hash = db.Column(db.String(255), nullable=False)
    role = db.Column(db.String(20), nullable=False, default="donor")  # donor | creator | admin
    verified = db.Column(db.Boolean, nullable=False, default=False)
    avatar_url = db.Column(db.String(255), nullable=True)
    created_at = db.Column(db.DateTime, nullable=False, default=datetime.utcnow)

    campaigns = db.relationship("Campaign", backref="creator", lazy=True)
    donations = db.relationship("Donation", backref="donor", lazy=True)

    def set_password(self, raw_password):
        self.password_hash = generate_password_hash(raw_password)

    def check_password(self, raw_password):
        return check_password_hash(self.password_hash, raw_password)

    def initials(self):
        parts = self.name.split()
        return "".join(p[0] for p in parts[:2]).upper() or "?"


# ---------------------------------------------------------------------------
# CAMPAIGNS
# ---------------------------------------------------------------------------
class Campaign(db.Model):
    __tablename__ = "campaigns"

    id = db.Column(db.Integer, primary_key=True)
    title = db.Column(db.String(150), nullable=False)
    description = db.Column(db.Text, nullable=False)
    category = db.Column(db.String(50), nullable=False, default="General")

    goal_amount = db.Column(db.Float, nullable=False)
    collected_amount = db.Column(db.Float, nullable=False, default=0)
    released_amount = db.Column(db.Float, nullable=False, default=0)

    image_url = db.Column(db.String(255), nullable=True)
    # Stored as a JSON-encoded list of extra image paths, kept simple with
    # a couple of helper methods below instead of a separate table.
    additional_images_json = db.Column(db.Text, nullable=False, default="[]")

    contact_phone = db.Column(db.String(50), nullable=True)
    contact_email = db.Column(db.String(120), nullable=True)
    contact_facebook = db.Column(db.String(255), nullable=True)
    contact_whatsapp = db.Column(db.String(50), nullable=True)

    status = db.Column(db.String(20), nullable=False, default="pending")  # pending|active|completed|rejected

    creator_id = db.Column(db.Integer, db.ForeignKey("users.id"), nullable=False)

    trust_score = db.Column(db.Integer, nullable=False, default=0)
    approved_proofs = db.Column(db.Integer, nullable=False, default=0)
    last_update_at = db.Column(db.DateTime, nullable=True)
    created_at = db.Column(db.DateTime, nullable=False, default=datetime.utcnow)

    donations = db.relationship("Donation", backref="campaign", lazy=True, cascade="all, delete-orphan")
    proofs = db.relationship("Proof", backref="campaign", lazy=True, cascade="all, delete-orphan")
    edits = db.relationship("CampaignEdit", backref="campaign", lazy=True, cascade="all, delete-orphan")
    withdrawals = db.relationship("WithdrawRequest", backref="campaign", lazy=True, cascade="all, delete-orphan")

    # -- helpers -----------------------------------------------------------
    def additional_images(self):
        try:
            return json.loads(self.additional_images_json or "[]")
        except (TypeError, ValueError):
            return []

    def all_images(self):
        return [img for img in [self.image_url] + self.additional_images() if img]

    def progress_percent(self):
        if not self.goal_amount:
            return 0
        return min(round((self.collected_amount / self.goal_amount) * 100), 100)

    def remaining_in_escrow(self):
        return self.collected_amount - self.released_amount

    def approved_donations(self):
        return [d for d in self.donations if d.status == "approved"]

    def approved_proof_list(self):
        return sorted(
            [p for p in self.proofs if p.status == "approved"],
            key=lambda p: p.created_at,
            reverse=True,
        )


# ---------------------------------------------------------------------------
# CAMPAIGN EDITS — a creator can request a description change; it only goes
# live after an admin approves it (keeps campaign text trustworthy).
# ---------------------------------------------------------------------------
class CampaignEdit(db.Model):
    __tablename__ = "campaign_edits"

    id = db.Column(db.Integer, primary_key=True)
    campaign_id = db.Column(db.Integer, db.ForeignKey("campaigns.id"), nullable=False)
    old_description = db.Column(db.Text, nullable=False)
    new_description = db.Column(db.Text, nullable=False)
    status = db.Column(db.String(20), nullable=False, default="pending")  # pending|approved|rejected
    created_at = db.Column(db.DateTime, nullable=False, default=datetime.utcnow)
    reviewed_at = db.Column(db.DateTime, nullable=True)


# ---------------------------------------------------------------------------
# DONATIONS — a donor "pledges" an amount via bKash/Nagad/bank transfer and
# submits the transaction ID. It only counts once an admin verifies it.
# ---------------------------------------------------------------------------
class Donation(db.Model):
    __tablename__ = "donations"

    id = db.Column(db.Integer, primary_key=True)
    campaign_id = db.Column(db.Integer, db.ForeignKey("campaigns.id"), nullable=False)
    donor_id = db.Column(db.Integer, db.ForeignKey("users.id"), nullable=False)

    amount = db.Column(db.Float, nullable=False)
    payment_method = db.Column(db.String(20), nullable=False)  # bkash|nagad|bank
    transaction_id = db.Column(db.String(100), nullable=False)
    message = db.Column(db.String(200), nullable=True)

    status = db.Column(db.String(20), nullable=False, default="pending")  # pending|approved|rejected
    admin_note = db.Column(db.String(255), nullable=True)

    # Filled in once an admin approves the donation and the event is
    # recorded via ledger.record_event() — see app.py.
    tx_hash = db.Column(db.String(120), nullable=True)

    created_at = db.Column(db.DateTime, nullable=False, default=datetime.utcnow)


# ---------------------------------------------------------------------------
# PROOFS — evidence (receipts, hospital bills, photos) that a creator
# uploads to show donors how the money is being used.
# ---------------------------------------------------------------------------
class Proof(db.Model):
    __tablename__ = "proofs"

    id = db.Column(db.Integer, primary_key=True)
    campaign_id = db.Column(db.Integer, db.ForeignKey("campaigns.id"), nullable=False)
    title = db.Column(db.String(150), nullable=False)
    description = db.Column(db.Text, nullable=False)
    file_url = db.Column(db.String(255), nullable=True)

    status = db.Column(db.String(20), nullable=False, default="pending")  # pending|approved|rejected
    admin_note = db.Column(db.String(255), nullable=True)
    tx_hash = db.Column(db.String(120), nullable=True)

    created_at = db.Column(db.DateTime, nullable=False, default=datetime.utcnow)


# ---------------------------------------------------------------------------
# WITHDRAW REQUESTS — a creator asks to move collected money out of escrow.
# Requires at least one approved proof, and an admin must approve it too.
# ---------------------------------------------------------------------------
class WithdrawRequest(db.Model):
    __tablename__ = "withdraw_requests"

    id = db.Column(db.Integer, primary_key=True)
    campaign_id = db.Column(db.Integer, db.ForeignKey("campaigns.id"), nullable=False)
    requested_amount = db.Column(db.Float, nullable=False)
    proof_id = db.Column(db.Integer, db.ForeignKey("proofs.id"), nullable=False)

    status = db.Column(db.String(20), nullable=False, default="pending")  # pending|approved|rejected
    admin_note = db.Column(db.String(255), nullable=True)
    tx_hash = db.Column(db.String(120), nullable=True)

    created_at = db.Column(db.DateTime, nullable=False, default=datetime.utcnow)

    proof = db.relationship("Proof")
