from __future__ import annotations

import hashlib
import json
from typing import Any

import requests

from config import CRMConfig


def _post(rest_url: str, payload: dict[str, Any], timeout: int = 10) -> dict[str, Any]:
    response = requests.post(rest_url, data=payload, timeout=timeout)
    response.raise_for_status()
    return response.json()


def login(crm: CRMConfig) -> str:
    payload = {
        "method": "login",
        "input_type": "JSON",
        "response_type": "JSON",
        "rest_data": json.dumps(
            {
                "user_auth": {
                    "user_name": crm.user,
                    "password": hashlib.md5(crm.password.encode()).hexdigest(),
                },
                "application_name": "intake",
            }
        ),
    }

    result = _post(crm.rest_url, payload, timeout=10)
    if "id" not in result:
        raise RuntimeError(f"login_failed: {result}")

    return str(result["id"])


def set_lead(session: str, crm: CRMConfig, row: dict[str, Any]) -> str:
    lead = {
        "last_name": row["name"],
        "account_name": row["company"],
        "email1": row["email"],
        "phone_work": row["phone"] or "",
        "industry": row["industry"] or "",
        "description": row["description"] or "",
        "lead_source": "Web Site",
        "status": "New",
    }

    payload = {
        "method": "set_entry",
        "input_type": "JSON",
        "response_type": "JSON",
        "rest_data": json.dumps(
            {
                "session": session,
                "module_name": "Leads",
                "name_value_list": [{"name": key, "value": value} for key, value in lead.items()],
            }
        ),
    }

    result = _post(crm.rest_url, payload, timeout=10)
    if "id" not in result:
        raise RuntimeError(f"set_entry_failed: {result}")

    return str(result["id"])
