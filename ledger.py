"""
ledger.py — THE BLOCKCHAIN INTEGRATION POINT
===============================================

TrustFundBD's whole pitch is transparency: donors want proof that their
money actually got verified and released. That's exactly what a
blockchain is good for — a public, tamper-proof record of events.

Right now, `record_event()` just appends events to a local JSON file
(chain_ledger.json). It behaves like a tiny fake blockchain: every event
gets a sequential "transaction hash" and is never edited or deleted, only
appended to. This keeps the app fully working and easy to read today.

--------------------------------------------------------------------
HOW TO PLUG IN A REAL BLOCKCHAIN LATER
--------------------------------------------------------------------
1. pip install web3
2. Deploy (or point to) a smart contract with a function like:
       function recordEvent(string eventType, string payloadHash) public
3. Replace the body of record_event() below with something like:

       from web3 import Web3
       w3 = Web3(Web3.HTTPProvider("https://your-rpc-url"))
       contract = w3.eth.contract(address=CONTRACT_ADDRESS, abi=CONTRACT_ABI)
       tx = contract.functions.recordEvent(event_type, json.dumps(payload)) \
                              .transact({"from": WALLET_ADDRESS})
       return tx.hex()   # <- this becomes the real on-chain tx hash

4. Nothing else in app.py needs to change. Every place that establishes
   trust (donation approved, proof approved, funds released) already
   calls record_event() and stores the returned hash in that record's
   `tx_hash` column, so it will show up in the UI automatically.
"""

import json
import os
import time

LEDGER_FILE = os.path.join(os.path.dirname(__file__), "chain_ledger.json")


def _load_events():
    if os.path.exists(LEDGER_FILE):
        try:
            with open(LEDGER_FILE, "r") as f:
                return json.load(f)
        except (json.JSONDecodeError, OSError):
            # If the file is empty or got cut off mid-write, don't let a
            # bad file crash every future approval — start a fresh ledger
            # instead of raising.
            return []
    return []


def record_event(event_type: str, payload: dict) -> str:
    """
    Record a trust event (donation_approved, proof_approved,
    withdrawal_approved, etc). Returns a transaction hash / reference
    string that gets stored alongside the record in the database.

    This is the ONE function you need to change to go from "local JSON
    file" to "real blockchain".
    """
    events = _load_events()

    tx_hash = f"local-{len(events) + 1:06d}"
    events.append({
        "tx_hash": tx_hash,
        "type": event_type,
        "payload": payload,
        "timestamp": time.time(),
    })

    with open(LEDGER_FILE, "w") as f:
        json.dump(events, f, indent=2)

    return tx_hash
