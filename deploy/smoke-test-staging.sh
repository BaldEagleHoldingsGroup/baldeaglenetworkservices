#!/usr/bin/env bash
set -euo pipefail

TARGET_HOST="${1:-avalanche}"

ssh "$TARGET_HOST" 'sudo bash -s' <<'REMOTE_SCRIPT'
set -euo pipefail

systemctl is-active --quiet bens-intake-api.service
systemctl is-active --quiet bens-crm-worker.service
curl -fsS http://127.0.0.1:5000/healthz >/dev/null

set -a
. /etc/bens-intake.env
set +a

/opt/bens-intake/shared/venv/bin/python - <<'PY'
import json
import os
import time

import pymysql
import requests

email = f"staging-smoke-{int(time.time())}@example.com"
payload = {
    "name": "Staging Smoke Test",
    "company": "BENS Staging",
    "email": email,
    "phone": "8015550100",
    "industry": "IT",
    "employee_count": "10",
    "description": "Staging deploy smoke test",
}

response = requests.post("http://127.0.0.1:5000/api/consult", json=payload, timeout=15)
response.raise_for_status()

conn = pymysql.connect(
    host=os.environ["DB_HOST"],
    port=int(os.environ["DB_PORT"]),
    user=os.environ["DB_USER"],
    password=os.environ["DB_PASSWORD"],
    database=os.environ["DB_NAME"],
    cursorclass=pymysql.cursors.DictCursor,
)

try:
    deadline = time.time() + 120
    while time.time() < deadline:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT id, processed, retry_count, last_error, crm_id
                FROM leads_queue
                WHERE email=%s
                ORDER BY id DESC
                LIMIT 1
                """,
                (email,),
            )
            row = cur.fetchone()

        if row and row["processed"] == 1 and row["crm_id"] and not row["last_error"]:
            print(json.dumps({"result": "ok", "email": email, "row": row}))
            break

        time.sleep(5)
    else:
        raise SystemExit(f"Smoke test did not complete successfully for {email}")
finally:
    conn.close()
PY
REMOTE_SCRIPT
