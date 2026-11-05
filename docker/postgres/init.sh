#!/bin/sh
# Dijalankan otomatis oleh image postgres saat volume masih kosong.
# Membuat database terpisah untuk test otomatis (RefreshDatabase menghapus isinya).
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<EOSQL
CREATE DATABASE sevima_ukt_test OWNER "$POSTGRES_USER";
EOSQL
