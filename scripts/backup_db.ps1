param([string]$BackupDir="C:\nali-backups")
New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null
$stamp=Get-Date -Format 'yyyyMMdd-HHmmss'; $target=Join-Path $BackupDir "nali-$stamp.sql"
docker compose exec -T db sh -lc 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' | Set-Content -Encoding utf8 $target
Get-ChildItem $BackupDir -Filter 'nali-*.sql' | Sort-Object LastWriteTime -Descending | Select-Object -Skip 30 | Remove-Item -Force
Write-Output "Created $target"
