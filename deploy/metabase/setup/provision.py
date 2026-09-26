#!/usr/bin/env python3
"""Provisioning idempoten untuk starter questions & dashboard Metabase tracer study.

Target: instance Metabase yang SUDAH berjalan di https://analyst.smart-tracer.id
(sudah pernah di-setup) -- script ini hanya login sebagai admin lewat API,
tidak memakai POST /api/setup.

Pakai Python stdlib saja (tidak perlu Node.js -- tidak terinstal di server ini).

Env var yang dibutuhkan:
  MB_ADMIN_EMAIL     - email admin Metabase
  MB_ADMIN_PASSWORD  - password admin Metabase
  MB_BASE_URL        - opsional, default https://analyst.smart-tracer.id

Jalankan:
  MB_ADMIN_EMAIL=... MB_ADMIN_PASSWORD=... python3 metabase/setup/provision.py

Aman dijalankan berulang kali: collection/card/dashboard dicari dulu berdasarkan
nama sebelum dibuat, dan layout dashboard dibangun ulang tanpa menduplikasi
dashcard yang sudah ada (dicocokkan lewat card_id).
"""
import json
import os
import sys
import urllib.error
import urllib.request

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
QUESTIONS_DIR = os.path.normpath(os.path.join(SCRIPT_DIR, "..", "questions"))

MB_BASE_URL = os.environ.get("MB_BASE_URL", "https://analyst.smart-tracer.id").rstrip("/")


def api(method, path, session=None, body=None):
    url = f"{MB_BASE_URL}{path}"
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(url, data=data, method=method)
    req.add_header("Content-Type", "application/json")
    if session:
        req.add_header("X-Metabase-Session", session)
    try:
        with urllib.request.urlopen(req) as resp:
            raw = resp.read()
            return json.loads(raw) if raw else None
    except urllib.error.HTTPError as e:
        raw = e.read().decode(errors="replace")
        raise RuntimeError(f"{method} {path} -> HTTP {e.code}: {raw}") from None


def login():
    email = os.environ.get("MB_ADMIN_EMAIL")
    password = os.environ.get("MB_ADMIN_PASSWORD")
    if not email or not password:
        sys.exit("MB_ADMIN_EMAIL dan MB_ADMIN_PASSWORD harus di-set sebagai env var.")
    result = api("POST", "/api/session", body={"username": email, "password": password})
    return result["id"]


def find_database_id(session, name):
    dbs = api("GET", "/api/database", session=session)["data"]
    for d in dbs:
        if d["name"] == name:
            return d["id"]
    raise RuntimeError(
        f'Database "{name}" tidak ditemukan di Metabase (Admin > Databases).'
    )


def find_or_create_collection(session, name, description):
    collections = api("GET", "/api/collection", session=session)
    for c in collections:
        if c.get("name") == name and not c.get("archived"):
            return c["id"]
    created = api(
        "POST",
        "/api/collection",
        session=session,
        body={"name": name, "description": description, "color": "#509EE3", "parent_id": None},
    )
    return created["id"]


def find_collection_item_id(session, collection_id, model, name):
    items = api(
        "GET", f"/api/collection/{collection_id}/items?models={model}", session=session
    )["data"]
    for item in items:
        if item.get("name") == name:
            return item["id"]
    return None


def build_template_tags(variables, tag_names):
    tags = {}
    for tag_name in tag_names:
        var = variables[tag_name]
        tags[tag_name] = {
            "id": f"tag-{tag_name}",
            "name": tag_name,
            "display-name": var["display_name"],
            "type": var.get("type", "text"),
            "default": None,
        }
    return tags


def upsert_card(session, collection_id, database_id, variables, question):
    sql_path = os.path.join(QUESTIONS_DIR, question["file"])
    with open(sql_path) as f:
        query_text = f.read()

    payload = {
        "name": question["name"],
        "description": question.get("description", ""),
        "collection_id": collection_id,
        "display": question.get("display", "table"),
        "visualization_settings": {},
        "dataset_query": {
            "type": "native",
            "database": database_id,
            "native": {
                "query": query_text,
                "template-tags": build_template_tags(variables, question.get("template_tags", [])),
            },
        },
    }

    existing_id = find_collection_item_id(session, collection_id, "card", question["name"])
    if existing_id:
        api("PUT", f"/api/card/{existing_id}", session=session, body=payload)
        return existing_id, "updated"
    created = api("POST", "/api/card", session=session, body=payload)
    return created["id"], "created"


def upsert_dashboard(session, collection_id, name, description):
    existing_id = find_collection_item_id(session, collection_id, "dashboard", name)
    if existing_id:
        return existing_id, "updated"
    created = api(
        "POST",
        "/api/dashboard",
        session=session,
        body={"name": name, "description": description, "collection_id": collection_id},
    )
    return created["id"], "created"


def build_dashboard_parameters(variables):
    return [
        {"id": f"param-{tag_name}", "name": var["display_name"], "slug": tag_name, "type": "string/="}
        for tag_name, var in variables.items()
    ]


def sync_dashboard_layout(session, dashboard_id, card_ids_by_name, variables, questions):
    current = api("GET", f"/api/dashboard/{dashboard_id}", session=session)
    existing_by_card = {dc["card_id"]: dc for dc in (current.get("dashcards") or [])}

    cols, card_w, card_h = 2, 12, 8
    dashcards = []
    for idx, question in enumerate(questions):
        card_id = card_ids_by_name[question["name"]]
        row, col = (idx // cols) * card_h, (idx % cols) * card_w
        parameter_mappings = [
            {
                "parameter_id": f"param-{tag_name}",
                "card_id": card_id,
                "target": ["variable", ["template-tag", tag_name]],
            }
            for tag_name in question.get("template_tags", [])
        ]
        existing = existing_by_card.get(card_id)
        dashcards.append(
            {
                "id": existing["id"] if existing else -(idx + 1),
                "card_id": card_id,
                "dashboard_tab_id": None,
                "row": existing["row"] if existing else row,
                "col": existing["col"] if existing else col,
                "size_x": existing["size_x"] if existing else card_w,
                "size_y": existing["size_y"] if existing else card_h,
                "series": [],
                "visualization_settings": existing.get("visualization_settings", {}) if existing else {},
                "parameter_mappings": parameter_mappings,
            }
        )

    api(
        "PUT",
        f"/api/dashboard/{dashboard_id}",
        session=session,
        body={"parameters": build_dashboard_parameters(variables), "dashcards": dashcards},
    )


def main():
    with open(os.path.join(QUESTIONS_DIR, "manifest.json")) as f:
        manifest = json.load(f)

    session = login()
    print("Login OK")

    database_id = find_database_id(session, manifest["database_name"])
    print(f'Database "{manifest["database_name"]}" -> id {database_id}')

    collection_id = find_or_create_collection(
        session, manifest["collection_name"], manifest.get("collection_description", "")
    )
    print(f'Collection "{manifest["collection_name"]}" -> id {collection_id}')

    card_ids_by_name = {}
    for question in manifest["questions"]:
        card_id, action = upsert_card(session, collection_id, database_id, manifest["variables"], question)
        card_ids_by_name[question["name"]] = card_id
        print(f'  [{action}] Card "{question["name"]}" -> id {card_id}')

    dashboard_id, action = upsert_dashboard(
        session, collection_id, manifest["dashboard_name"], manifest.get("collection_description", "")
    )
    print(f'[{action}] Dashboard "{manifest["dashboard_name"]}" -> id {dashboard_id}')

    sync_dashboard_layout(session, dashboard_id, card_ids_by_name, manifest["variables"], manifest["questions"])
    print("Layout dashboard tersinkron.")

    print(f"\nSelesai. Buka: {MB_BASE_URL}/dashboard/{dashboard_id}")


if __name__ == "__main__":
    main()
