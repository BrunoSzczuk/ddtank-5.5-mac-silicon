#!/usr/bin/env bash
# Restore the three DDT databases from /backup inside ddt-mssql.
# Idempotent: drops + recreates each database every run.
set -euo pipefail

CONTAINER=ddt-mssql
SA_PASS="ddtank@2016"

run_sqlcmd_file() {
  local local_file=$1
  chmod 644 "$local_file"
  docker cp "$local_file" "$CONTAINER:/tmp/$(basename "$local_file")" >/dev/null
  docker exec "$CONTAINER" /opt/mssql-tools18/bin/sqlcmd \
    -S localhost -U sa -P "$SA_PASS" -No -b \
    -i "/tmp/$(basename "$local_file")"
}

wait_for_mssql() {
  echo "Waiting for SQL Server to accept connections..."
  for _ in $(seq 1 60); do
    if docker exec "$CONTAINER" /opt/mssql-tools18/bin/sqlcmd \
      -S localhost -U sa -P "$SA_PASS" -No -Q "SELECT 1" >/dev/null 2>&1; then
      echo "SQL Server is up."
      return 0
    fi
    sleep 2
  done
  echo "Timeout waiting for SQL Server." >&2
  exit 1
}

restore_db() {
  local db=$1
  local bak=$2
  local tmpfile
  tmpfile=$(mktemp -t "restore-${db}.XXXXXX.sql")
  cat > "$tmpfile" <<EOSQL
SET NOCOUNT ON;
IF DB_ID(N'$db') IS NOT NULL
BEGIN
    ALTER DATABASE [$db] SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
    DROP DATABASE [$db];
END;

DECLARE @filelist TABLE (
    LogicalName nvarchar(128), PhysicalName nvarchar(260), Type char(1),
    FileGroupName nvarchar(128), Size numeric(20,0), MaxSize numeric(20,0),
    FileId bigint, CreateLSN numeric(25,0), DropLSN numeric(25,0), UniqueId uniqueidentifier,
    ReadOnlyLSN numeric(25,0), ReadWriteLSN numeric(25,0), BackupSizeInBytes bigint,
    SourceBlockSize int, FileGroupId int, LogGroupGUID uniqueidentifier,
    DifferentialBaseLSN numeric(25,0), DifferentialBaseGUID uniqueidentifier,
    IsReadOnly bit, IsPresent bit, TDEThumbprint varbinary(32), SnapshotUrl nvarchar(360)
);
INSERT INTO @filelist EXEC('RESTORE FILELISTONLY FROM DISK = ''/backup/$bak''');

DECLARE @move nvarchar(max) = N'';
SELECT @move = @move + N', MOVE N''' + LogicalName + N''' TO N''/var/opt/mssql/data/${db}_' + LogicalName +
    CASE WHEN Type = 'L' THEN '.ldf' ELSE '.mdf' END + N''''
FROM @filelist;

DECLARE @sql nvarchar(max) =
    N'RESTORE DATABASE [$db] FROM DISK = ''/backup/$bak'' WITH FILE = 1' + @move +
    N', REPLACE, STATS = 25';
PRINT @sql;
EXEC(@sql);
GO
EOSQL
  echo "==> Restoring $db from $bak"
  run_sqlcmd_file "$tmpfile"
  rm -f "$tmpfile"
}

wait_for_mssql

restore_db "Db_Tank"       "Db_Tank.bak"
restore_db "Db_Membership" "Db_Membership.bak"
restore_db "Db_Count"      "Db_Count.bak"

echo ""
echo "==> Database list:"
docker exec "$CONTAINER" /opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P "$SA_PASS" -No \
  -Q "SELECT name, state_desc, recovery_model_desc FROM sys.databases ORDER BY name"

echo ""
echo "==> Tables in Db_Tank (first 15):"
docker exec "$CONTAINER" /opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P "$SA_PASS" -No -d Db_Tank \
  -Q "SELECT TOP 15 TABLE_SCHEMA + '.' + TABLE_NAME AS table_name FROM INFORMATION_SCHEMA.TABLES ORDER BY TABLE_NAME"
