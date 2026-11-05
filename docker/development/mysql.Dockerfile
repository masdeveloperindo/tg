FROM mysql:8.4@sha256:a81d043930e51745522bd5c430961cf8286f0a23f0a9a2a6e0108f7ea71b0902

# A restrictive checkout umask must not prevent the mysql user reading init SQL.
COPY --chmod=0444 01-test-database.sql /docker-entrypoint-initdb.d/01-test-database.sql
