#!/usr/bin/env python3
from __future__ import annotations

import time

from config import load_worker_config
from db import connect, fetch_unprocessed_rows, mark_failed, mark_processed
from suitecrm import login, set_lead

CONFIG = load_worker_config()


def loop() -> None:
    while True:
        conn = connect(CONFIG.database)
        try:
            rows = fetch_unprocessed_rows(conn)

            if not rows:
                conn.commit()
                time.sleep(5)
                continue

            session = login(CONFIG.crm)

            for row in rows:
                try:
                    crm_id = set_lead(session, CONFIG.crm, row)
                    mark_processed(conn, int(row["id"]), crm_id)
                except Exception as exc:
                    mark_failed(conn, int(row["id"]), str(exc))

            conn.commit()
        finally:
            conn.close()

        time.sleep(2)


if __name__ == "__main__":
    loop()
