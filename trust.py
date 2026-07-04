"""
trust.py — Trust score calculation
=====================================

This is a direct Python port of the `recalculate_trust_score()` SQL
function from the original Supabase schema. It runs every time something
that affects trust changes: a proof gets approved, a donation gets
approved, or an admin verifies the creator.

Score breakdown (out of 100):
    +25  the campaign creator's identity is verified
    +25  up to 25 points for approved proofs (8 pts each, capped at 25)
    +25  the campaign has posted an update within the last 7 days
    +25  donation approval rate (approved donations / total donations)
"""

from datetime import datetime, timedelta


def recalculate_trust_score(campaign):
    """Recompute campaign.trust_score in place. Caller is responsible for
    committing the change to the database."""
    score = 0

    # 1. Verified creator identity
    if campaign.creator and campaign.creator.verified:
        score += 25

    # 2. Approved proofs (max 25 points, 8 points each)
    score += min(campaign.approved_proofs * 8, 25)

    # 3. Recent update within the last 7 days
    if campaign.last_update_at and campaign.last_update_at > datetime.utcnow() - timedelta(days=7):
        score += 25

    # 4. Donation approval rate
    total = len(campaign.donations)
    approved = len(campaign.approved_donations())
    if total > 0:
        score += round((approved / total) * 25)

    campaign.trust_score = min(score, 100)
    return campaign.trust_score
