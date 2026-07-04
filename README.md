# TrustFundBD — Python (Flask) Edition

A simple, single-purpose Flask rewrite of the TrustFundBD crowdfunding platform
(originally Next.js + Supabase). Same flow, same trust rules, plain Python —
written to be easy to read and easy to extend with blockchain.

## Run it

```bash
pip install -r requirements.txt
python app.py
```

Then open http://127.0.0.1:5000 — the database (`trustfundbd.db`) is created
automatically on first run.

## The flow

1. Register as a donor, creator, or **Admin (Demo)** — the demo admin option
   on the register page is intentional, so you can test approvals yourself.
2. A creator starts a campaign → it's `pending`.
3. An admin approves it → it goes `active` and can take donations.
4. A donor picks an amount, "sends" money via bKash/Nagad/bank, and submits
   a transaction ID → the donation is `pending`.
5. An admin manually verifies it and approves → `collected_amount` goes up
   and the campaign's trust score is recalculated.
6. The creator uploads proof of fund usage → admin approves → trust score
   goes up again.
7. Once there's an approved proof, the creator can request a withdrawal
   (up to collected − released) → admin approves → `released_amount` goes
   up. This is money actually leaving escrow.

## Where blockchain plugs in

Every trust-establishing moment above (`donation_approved`, `proof_approved`,
`withdrawal_approved`) calls `ledger.record_event()` in `ledger.py`. Right
now that just appends to a local `chain_ledger.json` file and returns a fake
transaction hash — a stand-in "chain" that already mirrors the shape a real
one would have.

To go live with real blockchain:
1. `pip install web3`
2. Replace the body of `record_event()` in `ledger.py` with a call to your
   smart contract.
3. Nothing else changes — `app.py` already stores whatever hash
   `record_event()` returns on the `tx_hash` column of the donation, proof,
   or withdrawal record, and the templates already display it when present.

## Project layout

- `app.py` — all routes (auth, public pages, donor/creator/admin flows)
- `models.py` — database tables (User, Campaign, Donation, Proof, CampaignEdit, WithdrawRequest)
- `trust.py` — the trust score formula
- `ledger.py` — the blockchain integration seam described above
- `templates/` — server-rendered pages (Tailwind via CDN, no build step)

## Known simplifications vs. the original Next.js app

To keep this "basic" and readable, a few things were simplified rather than
pixel-for-pixel ported:
- No rich-text editor for campaign descriptions (plain text only).
- No campaign image carousel — a single main image plus a thumbnail row.
- No "contact creator" popup — contact info is shown directly on the page.
- File uploads (campaign images, proof documents) are saved to
  `static/uploads/` on disk instead of cloud storage.
- No profile-editing page or report/flagging feature (unused in the original
  app's live pages anyway).

Everything else — roles, campaign lifecycle, donation verification, proof
review, withdrawal rules, and the trust score formula — works the same way
as the original app.
