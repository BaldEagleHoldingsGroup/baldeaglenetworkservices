#!/usr/bin/env python3
from __future__ import annotations

import logging
import re
from typing import Any

from flask import Flask, jsonify, request
from pymysql.err import MySQLError

from config import load_intake_api_config
from db import connect, database_healthcheck, insert_lead_queue_row

app = Flask(__name__)

CONFIG = load_intake_api_config()

logging.basicConfig(
    level=CONFIG.log_level,
    format="%(asctime)s %(levelname)s %(name)s: %(message)s",
)
logger = logging.getLogger("bens-intake-api")
logger.setLevel(CONFIG.log_level)

EMAIL_RE = re.compile(r"^[^@\s]+@[^@\s]+\.[^@\s]+$")
PHONE_RE = re.compile(r"^[0-9+\-().\s]{7,30}$")

MAX_NAME_LEN = 150
MAX_COMPANY_LEN = 150
MAX_EMAIL_LEN = 254
MAX_PHONE_LEN = 30
MAX_INDUSTRY_LEN = 120
MAX_EMPLOYEE_COUNT_LEN = 50
MAX_DESCRIPTION_LEN = 2000


def clean_str(value: Any, max_len: int) -> str:
    if value is None:
        return ""
    if not isinstance(value, str):
        value = str(value)
    return value.strip()[:max_len]


def validate_payload(data: dict[str, Any]) -> tuple[dict[str, str], dict[str, str]]:
    name = clean_str(data.get("name"), MAX_NAME_LEN)
    company = clean_str(data.get("company"), MAX_COMPANY_LEN)
    email = clean_str(data.get("email"), MAX_EMAIL_LEN).lower()
    phone = clean_str(data.get("phone"), MAX_PHONE_LEN)
    industry = clean_str(data.get("industry"), MAX_INDUSTRY_LEN)
    employee_count = clean_str(data.get("employee_count"), MAX_EMPLOYEE_COUNT_LEN)
    description = clean_str(data.get("description"), MAX_DESCRIPTION_LEN)

    errors: dict[str, str] = {}

    if not name:
        errors["name"] = "Name is required."
    if not company:
        errors["company"] = "Company is required."
    if not email:
        errors["email"] = "Email is required."
    elif not EMAIL_RE.match(email):
        errors["email"] = "Invalid email address."

    if phone and not PHONE_RE.match(phone):
        errors["phone"] = "Invalid phone number."

    if not description:
        errors["description"] = "Description is required."

    payload = {
        "name": name,
        "company": company,
        "email": email,
        "phone": phone,
        "industry": industry,
        "employee_count": employee_count,
        "description": description,
    }

    return payload, errors


@app.get("/healthz")
def healthz():
    conn = None
    try:
        conn = connect(CONFIG.database)
        database_healthcheck(conn)
        return jsonify({"status": "ok"}), 200
    except MySQLError:
        logger.exception("health check failed")
        return jsonify({"status": "error", "message": "database unavailable"}), 503
    finally:
        if conn is not None:
            try:
                conn.close()
            except Exception:
                pass


@app.post("/api/consult")
def consult():
    if not request.is_json:
        return jsonify({"error": "json_required"}), 415

    data = request.get_json(silent=True)
    if not isinstance(data, dict):
        return jsonify({"error": "invalid_json"}), 400

    payload, errors = validate_payload(data)
    if errors:
        return jsonify({"error": "validation_failed", "errors": errors}), 400

    conn = None
    try:
        conn = connect(CONFIG.database)
        lead_id = insert_lead_queue_row(conn, payload)
        conn.commit()
        return jsonify({"status": "queued", "id": lead_id}), 202

    except MySQLError:
        logger.exception("database insert failed")
        if conn is not None:
            try:
                conn.rollback()
            except Exception:
                pass
        return jsonify({"error": "database_error"}), 502

    except Exception:
        logger.exception("unexpected error in /api/consult")
        if conn is not None:
            try:
                conn.rollback()
            except Exception:
                pass
        return jsonify({"error": "internal_error"}), 500

    finally:
        if conn is not None:
            try:
                conn.close()
            except Exception:
                pass


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5000)
