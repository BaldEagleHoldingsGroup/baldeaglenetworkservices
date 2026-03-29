from __future__ import annotations

import os
from dataclasses import dataclass


@dataclass(frozen=True)
class DatabaseConfig:
    host: str
    port: int
    name: str
    user: str
    password: str
    charset: str


@dataclass(frozen=True)
class IntakeAPIConfig:
    database: DatabaseConfig
    log_level: str


@dataclass(frozen=True)
class CRMConfig:
    base: str
    user: str
    password: str

    @property
    def rest_url(self) -> str:
        return f"{self.base}/service/v4_1/rest.php"


@dataclass(frozen=True)
class WorkerConfig:
    database: DatabaseConfig
    crm: CRMConfig


def get_env(name: str, default: str | None = None) -> str:
    value = os.getenv(name, default)
    if value is None or value == "":
        raise RuntimeError(f"Missing required environment variable: {name}")
    return value


def load_database_config() -> DatabaseConfig:
    return DatabaseConfig(
        host=get_env("DB_HOST"),
        port=int(get_env("DB_PORT")),
        name=get_env("DB_NAME"),
        user=get_env("DB_USER"),
        password=get_env("DB_PASSWORD"),
        charset=os.getenv("DB_CHARSET", "utf8mb4"),
    )


def load_intake_api_config() -> IntakeAPIConfig:
    return IntakeAPIConfig(
        database=load_database_config(),
        log_level=os.getenv("LOG_LEVEL", "INFO").upper(),
    )


def load_worker_config() -> WorkerConfig:
    return WorkerConfig(
        database=load_database_config(),
        crm=CRMConfig(
            base=get_env("CRM_BASE"),
            user=get_env("CRM_USER"),
            password=get_env("CRM_PASS"),
        ),
    )
