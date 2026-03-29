from __future__ import annotations

from typing import Any

import pymysql
from pymysql.connections import Connection
from pymysql.cursors import DictCursor

from config import DatabaseConfig


def connect(database: DatabaseConfig) -> Connection:
    return pymysql.connect(
        host=database.host,
        port=database.port,
        user=database.user,
        password=database.password,
        database=database.name,
        charset=database.charset,
        cursorclass=DictCursor,
        autocommit=False,
        connect_timeout=5,
        read_timeout=10,
        write_timeout=10,
    )


def database_healthcheck(conn: Connection) -> None:
    with conn.cursor() as cur:
        cur.execute("SELECT 1 AS ok")
        cur.fetchone()


def insert_lead_queue_row(conn: Connection, payload: dict[str, str]) -> int:
    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO leads_queue
                (name, company, email, phone, industry, employee_count, description)
            VALUES
                (%s, %s, %s, %s, %s, %s, %s)
            """,
            (
                payload["name"],
                payload["company"],
                payload["email"],
                payload["phone"],
                payload["industry"],
                payload["employee_count"],
                payload["description"],
            ),
        )
        return int(cur.lastrowid)


def fetch_unprocessed_rows(conn: Connection) -> list[dict[str, Any]]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT *
            FROM leads_queue
            WHERE processed = 0
            ORDER BY id ASC
            LIMIT 10
            """
        )
        rows = cur.fetchall()
    return list(rows)


def mark_processed(conn: Connection, row_id: int, crm_id: str) -> None:
    with conn.cursor() as cur:
        cur.execute(
            """
            UPDATE leads_queue
            SET processed = 1,
                processed_at = NOW(),
                crm_id = %s,
                last_error = NULL
            WHERE id = %s
            """,
            (crm_id, row_id),
        )


def mark_failed(conn: Connection, row_id: int, error_message: str) -> None:
    with conn.cursor() as cur:
        cur.execute(
            """
            UPDATE leads_queue
            SET last_error = %s,
                retry_count = retry_count + 1
            WHERE id = %s
            """,
            (error_message[:500], row_id),
        )
