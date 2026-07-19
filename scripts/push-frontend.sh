#!/usr/bin/env bash
set -euo pipefail

CONFIG=deploy.config.json
j() { jq -r "$1" "$CONFIG"; }

FTP_HOST="$(j '.ftp.host')"
FTP_PORT="$(j '.ftp.port // 21')"
FTP_USER="$(j '.ftp.username')"
FTP_PASS="$(j '.ftp.password')"
FTP_SECURE="$(j '.ftp.secure // true')"
FTP_VERIFY="$(j '.ftp.verify_certificate // false')"
REMOTE_DIR="$(j '.ftp.remote_dir')"
SSH_HOST="$(j '.ssh.host')"
SSH_PORT="$(j '.ssh.port')"
SSH_USER="$(j '.ssh.username')"
SSH_PASS="$(j '.ssh.password')"
APP_DIR="$(j '.ssh.app_dir')"
PHP_BIN="$(j '.ssh.php_binary')"

echo "==> Uploading public/build via FTPS"
lftp <<LFTP
set cmd:fail-exit true;
set net:max-retries 3;
set net:timeout 25;
set xfer:use-temp-file false;
set xfer:clobber true;
set ftp:ssl-force ${FTP_SECURE};
set ftp:ssl-protect-data true;
set ssl:verify-certificate ${FTP_VERIFY};
open -p ${FTP_PORT} -u "${FTP_USER}","${FTP_PASS}" ftp://${FTP_HOST};
mirror -R --parallel=2 --no-perms --delete \
  "public/build/" "${REMOTE_DIR}/public/build";
bye;
LFTP

echo "==> Clearing view cache"
sshpass -p "${SSH_PASS}" ssh \
  -o StrictHostKeyChecking=no \
  -o UserKnownHostsFile=/dev/null \
  -o LogLevel=ERROR \
  -p "${SSH_PORT}" \
  "${SSH_USER}@${SSH_HOST}" \
  "${PHP_BIN} ${APP_DIR}/artisan view:clear && ls ${APP_DIR}/public/build/assets/index-*.js && echo ASSETS_DEPLOYED"
