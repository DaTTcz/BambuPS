#!/usr/bin/env bash
#
# BambuPS – instalátor pro čistý (fresh) server
# =============================================
# Stáhne a nastaví kompletní BambuPS stack na čerstvém Ubuntu/Debian serveru:
# PHP, nginx, MariaDB, Node.js, Composer, Supervisor, ffmpeg, go2rtc,
# naklonuje appku z GitHubu, vytvoří databázi, self-signed SSL certifikát
# a na konci tě interaktivně provede vytvořením prvního admin účtu.
#
# Použití:
#   curl -O https://raw.githubusercontent.com/DaTTcz/BambuPS/main/install-clean.sh
#   sudo bash install-clean.sh
#
# Skript je určen pro ČISTOU instalaci na server, kde appka ještě neběží.
# Aktualizace na novější verze appka řeší sama, přes web (tlačítko
# "aktualizovat" v horním menu) – tenhle skript se pro update nepoužívá.

set -euo pipefail

INSTALLER_VERSION="1.0.0"
REPO_URL="https://github.com/DaTTcz/BambuPS.git"
GITHUB_REPO="DaTTcz/BambuPS"
GO2RTC_REPO="AlexxIT/go2rtc"

INSTALL_ROOT="/opt/bambups"
APP_DIR="${INSTALL_ROOT}/app"
GO2RTC_DIR="${INSTALL_ROOT}/go2rtc"
PHP_VERSION="8.5"
NODE_MAJOR="22"

INFO_FILE="/root/bambups-install-info.txt"

# ---------------------------------------------------------------------------
# Pomocné funkce
# ---------------------------------------------------------------------------

c_reset="\033[0m"; c_bold="\033[1m"; c_green="\033[32m"; c_yellow="\033[33m"; c_red="\033[31m"; c_blue="\033[36m"

log()  { echo -e "${c_blue}==>${c_reset} ${c_bold}$*${c_reset}"; }
ok()   { echo -e "${c_green}✔${c_reset} $*"; }
warn() { echo -e "${c_yellow}⚠${c_reset} $*"; }
die()  { echo -e "${c_red}✘ $*${c_reset}" >&2; exit 1; }

on_error() {
    local exit_code=$?
    local line_no=$1
    echo
    die "Instalace selhala na řádku ${line_no} (exit kód ${exit_code}). Nic dalšího se nespustilo, oprav prosím chybu výše a spusť skript znovu."
}
trap 'on_error $LINENO' ERR

ask() {
    # ask "otázka" "výchozí_hodnota" -> vypíše zadanou hodnotu (nebo default při Enter)
    local prompt="$1" default="${2:-}" reply
    if [[ -n "$default" ]]; then
        read -r -p "$(echo -e "${c_bold}${prompt}${c_reset} [${default}]: ")" reply || true
        echo "${reply:-$default}"
    else
        read -r -p "$(echo -e "${c_bold}${prompt}${c_reset}: ")" reply || true
        echo "$reply"
    fi
}

random_secret() {
    # bezpečný alfanumerický řetězec bez znaků, co dělají problémy v .env / shellu
    openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c "${1:-32}"
}

is_ip() {
    [[ "$1" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]]
}

require_root() {
    if [[ "${EUID}" -ne 0 ]]; then
        die "Tenhle skript musí běžet jako root. Spusť ho znovu přes: sudo bash $0"
    fi
}

# ---------------------------------------------------------------------------
# 0) Kontroly a úvodní otázky
# ---------------------------------------------------------------------------

require_root

OS_ID=""
OS_CODENAME=""
if [[ -f /etc/os-release ]]; then
    . /etc/os-release
    OS_ID="${ID:-}"
    # VERSION_CODENAME chybí na Debian testing/sid - dopočítáme podle verze.
    OS_CODENAME="${VERSION_CODENAME:-}"
    case "$OS_ID" in
        ubuntu|debian)
            : # podporováno, viz níže
            ;;
        *)
            warn "Tenhle skript je testovaný na Ubuntu a Debian. Detekovaný systém: ${PRETTY_NAME:-neznámý}."
            [[ "$(ask 'Pokračovat i tak? (ano/ne)' 'ne')" == "ano" ]] || die "Instalace zrušena."
            ;;
    esac
else
    warn "Nepodařilo se detekovat verzi systému, pokračuji na vlastní riziko."
fi

if [[ -d "$APP_DIR" ]]; then
    die "Složka ${APP_DIR} už existuje – vypadá to, že appka je už nainstalovaná. Tenhle skript je jen pro ČISTOU instalaci na nový server. Pro aktualizaci použij tlačítko 'aktualizovat' v appce."
fi

echo
echo -e "${c_bold}=== BambuPS – instalátor v${INSTALLER_VERSION} ===${c_reset}"
echo "Odpověz na pár otázek, zbytek appka a instalátor zařídí za tebe."
echo

DEFAULT_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
SERVER_ADDRESS="$(ask 'Webová adresa serveru (IP nebo doména, na které bude appka dostupná)' "${DEFAULT_IP}")"
[[ -n "$SERVER_ADDRESS" ]] || die "Adresa serveru je povinná."
[[ "$SERVER_ADDRESS" =~ ^[A-Za-z0-9.-]+$ ]] || die "Adresa smí obsahovat jen písmena, čísla, tečky a pomlčky (IP nebo doména bez http(s):// a bez cesty)."

HTTPS_PORT="$(ask 'HTTPS port appky' '443')"
[[ "$HTTPS_PORT" =~ ^[0-9]+$ ]] || die "Port musí být číslo."

echo
log "Shrnutí:"
echo "  Adresa appky: https://${SERVER_ADDRESS}:${HTTPS_PORT}"
echo "  Instalační složka: ${APP_DIR}"
echo "  PHP: ${PHP_VERSION}"
echo
[[ "$(ask 'Spustit instalaci s těmito hodnotami? (ano/ne)' 'ano')" == "ano" ]] || die "Instalace zrušena."

# ---------------------------------------------------------------------------
# 1) Systémové závislosti
# ---------------------------------------------------------------------------

log "Aktualizuji seznam balíčků..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y -qq

log "Instaluji základní nástroje..."
# 'sudo' na holém Debianu (např. z netinst ISO) často není přednainstalované -
# appka ho ale sama potřebuje za běhu (www-data si přes něj ovládá supervisorctl,
# viz krok 8 níže), tak ho instalujeme vždycky výslovně, i když už běžíme jako root.
apt-get install -y -qq software-properties-common ca-certificates curl gnupg lsb-release unzip git openssl apt-transport-https sudo

if [[ -z "$OS_CODENAME" ]]; then
    OS_CODENAME="$(lsb_release -sc 2>/dev/null || true)"
fi

log "Přidávám repozitář pro PHP ${PHP_VERSION}..."
case "$OS_ID" in
    ubuntu)
        # ondrej/php PPA - funguje jen na Ubuntu (Launchpad), ne na Debianu.
        if ! grep -rq "ondrej/php" /etc/apt/sources.list.d/ 2>/dev/null; then
            LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php >/dev/null
            apt-get update -y -qq
        fi
        ;;
    debian)
        # Na Debianu ppa: nefunguje - použijeme oficiální balíčky Ondřeje Surého
        # přímo pro Debian (packages.sury.org), stejný zdroj balíčků, jiná cesta.
        [[ -n "$OS_CODENAME" ]] || die "Nepodařilo se zjistit kódové jméno Debianu (VERSION_CODENAME), nemůžu přidat repozitář s PHP."
        if [[ ! -f /etc/apt/sources.list.d/php.list ]]; then
            install -d -m 0755 /usr/share/keyrings
            curl -fsSL -o /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
            echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ ${OS_CODENAME} main" \
                > /etc/apt/sources.list.d/php.list
            apt-get update -y -qq
        fi
        ;;
    *)
        die "Nepodporovaná distribuce pro automatickou instalaci PHP ${PHP_VERSION}: ${OS_ID:-neznámá}. Nainstaluj PHP ${PHP_VERSION} ručně a spusť skript znovu."
        ;;
esac

log "Instaluji PHP ${PHP_VERSION} a potřebná rozšíření..."
apt-get install -y -qq \
    "php${PHP_VERSION}" "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" \
    "php${PHP_VERSION}-mysql" "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-curl" "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-gd" \
    "php${PHP_VERSION}-zip" "php${PHP_VERSION}-intl" "php${PHP_VERSION}-opcache" \
    "php${PHP_VERSION}-common"
PHP_FPM_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"

log "Instaluji nginx..."
apt-get install -y -qq nginx

log "Instaluji MariaDB..."
apt-get install -y -qq mariadb-server mariadb-client

log "Instaluji Supervisor a ffmpeg..."
apt-get install -y -qq supervisor ffmpeg

if ! command -v node >/dev/null 2>&1 || [[ "$(node -v | sed 's/^v//;s/\..*//')" -lt "$NODE_MAJOR" ]]; then
    log "Instaluji Node.js ${NODE_MAJOR}.x..."
    curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | bash - >/dev/null
    apt-get install -y -qq nodejs
fi

if ! command -v composer >/dev/null 2>&1; then
    log "Instaluji Composer..."
    EXPECTED_SIG="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL -o /tmp/composer-setup.php https://getcomposer.org/installer
    ACTUAL_SIG="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    [[ "$EXPECTED_SIG" == "$ACTUAL_SIG" ]] || die "Composer instalátor má neplatný podpis, přerušuji (možný problém se sítí/MITM)."
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer >/dev/null
    rm -f /tmp/composer-setup.php
fi
export COMPOSER_ALLOW_SUPERUSER=1

ok "Systémové závislosti nainstalovány."

# ---------------------------------------------------------------------------
# 2) MariaDB – databáze a uživatel appky
# ---------------------------------------------------------------------------

log "Vytvářím databázi a uživatele pro appku..."
systemctl enable --now mariadb >/dev/null

DB_NAME="bambups"
DB_USER="bambups"
DB_PASS="$(random_secret 32)"

mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
DELETE FROM mysql.user WHERE User='';
FLUSH PRIVILEGES;
SQL

ok "Databáze '${DB_NAME}' a uživatel '${DB_USER}' vytvořeni."

# ---------------------------------------------------------------------------
# 3) Naklonování appky z GitHubu (nejnovější release tag)
# ---------------------------------------------------------------------------

log "Zjišťuji nejnovější verzi appky na GitHubu..."
LATEST_TAG="$(git ls-remote --tags --refs "$REPO_URL" | awk -F/ '{print $NF}' | sort -V | tail -n1)"
if [[ -z "$LATEST_TAG" ]]; then
    warn "Nenašel jsem žádný release tag, klonuji main branch."
    LATEST_TAG="main"
fi
log "Instaluji verzi: ${LATEST_TAG}"

mkdir -p "$INSTALL_ROOT"
git clone --branch "$LATEST_TAG" --depth 1 "$REPO_URL" "$APP_DIR"
cd "$APP_DIR"

# Laravel potřebuje tyhle složky pro zápis cache/session/log souborů. Git
# prázdné složky sám o sobě netrackuje - v repu je drží držák (.gitignore
# soubor uvnitř každé z nich). Pro jistotu je vytvoříme i tak, kdyby v
# repu (třeba po forku bez nich) chyběly - composer/artisan by jinak
# hned na začátku spadly na "No such file or directory".
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

ok "Appka naklonovaná do ${APP_DIR}."

# ---------------------------------------------------------------------------
# 4) go2rtc (streamování kamer)
# ---------------------------------------------------------------------------

log "Stahuji go2rtc..."
mkdir -p "$GO2RTC_DIR"

ARCH="$(uname -m)"
case "$ARCH" in
    x86_64)  GO2RTC_ASSET="go2rtc_linux_amd64" ;;
    aarch64) GO2RTC_ASSET="go2rtc_linux_arm64" ;;
    armv7l)  GO2RTC_ASSET="go2rtc_linux_arm" ;;
    *) die "Nepodporovaná architektura pro go2rtc: ${ARCH}" ;;
esac

curl -fsSL -o "${GO2RTC_DIR}/go2rtc" \
    "https://github.com/${GO2RTC_REPO}/releases/latest/download/${GO2RTC_ASSET}"
chmod +x "${GO2RTC_DIR}/go2rtc"

cat > "${GO2RTC_DIR}/go2rtc.yaml" <<'YAML'
api:
  listen: :1984

rtsp:
  listen: :8554

streams:
YAML

ok "go2rtc připraveno."

# ---------------------------------------------------------------------------
# 5) .env appky
# ---------------------------------------------------------------------------

log "Vytvářím .env..."
cp .env.example .env

APP_KEY_PLACEHOLDER_URL="https://${SERVER_ADDRESS}:${HTTPS_PORT}"

# Poznámka: v .env.example jsou DB_* řádky zakomentované (# DB_HOST=...),
# protože výchozí dev konfigurace je sqlite. Vzor "^#? ?KLIC=" proto sedne
# na oba případy (zakomentovaný i ne) jedním pravidlem. Delimiter "/" (ne
# "#"), protože "#" je součástí hledaného vzoru u zakomentovaných řádků.
sed -i -E \
    -e "s/^APP_NAME=.*/APP_NAME=BambuPS/" \
    -e "s/^APP_ENV=.*/APP_ENV=production/" \
    -e "s/^APP_DEBUG=.*/APP_DEBUG=false/" \
    -e "s#^APP_URL=.*#APP_URL=${APP_KEY_PLACEHOLDER_URL}#" \
    -e "s/^APP_LOCALE=.*/APP_LOCALE=cs/" \
    -e "s/^APP_FALLBACK_LOCALE=.*/APP_FALLBACK_LOCALE=en/" \
    -e "s/^DB_CONNECTION=.*/DB_CONNECTION=mysql/" \
    -e "s/^#? ?DB_HOST=.*/DB_HOST=127.0.0.1/" \
    -e "s/^#? ?DB_PORT=.*/DB_PORT=3306/" \
    -e "s/^#? ?DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/" \
    -e "s/^#? ?DB_USERNAME=.*/DB_USERNAME=${DB_USER}/" \
    -e "s/^#? ?DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" \
    .env

# BambuPS-specifické klíče (github repo + cesta k appce pro auto-update)
if grep -q '^BAMBUPS_GITHUB_REPO=' .env; then
    sed -i "s#^BAMBUPS_GITHUB_REPO=.*#BAMBUPS_GITHUB_REPO=${GITHUB_REPO}#" .env
else
    echo "BAMBUPS_GITHUB_REPO=${GITHUB_REPO}" >> .env
fi
if grep -q '^BAMBUPS_APP_PATH=' .env; then
    sed -i "s#^BAMBUPS_APP_PATH=.*#BAMBUPS_APP_PATH=${APP_DIR}#" .env
else
    echo "BAMBUPS_APP_PATH=${APP_DIR}" >> .env
fi

ok ".env vytvořen."

# ---------------------------------------------------------------------------
# 6) PHP a JS závislosti, build frontendu
# ---------------------------------------------------------------------------

log "Instaluji PHP závislosti (composer install)..."
composer install --no-dev --optimize-autoloader --no-interaction

log "Instaluji JS závislosti a builduji frontend (může chvíli trvat)..."
npm install --no-audit --no-fund
npm run build

log "Generuji APP_KEY, migruji databázi..."
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\ModuleSeeder --force
php artisan storage:link

ok "Appka nastavena."

# ---------------------------------------------------------------------------
# 7) Oprávnění
# ---------------------------------------------------------------------------

log "Nastavuji oprávnění..."
chown -R www-data:www-data "$INSTALL_ROOT"
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 775 {} \;
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type f -exec chmod 664 {} \;

# app (přes CameraProvisionService) si sama zapisuje supervisor konfigy
# a go2rtc.yaml jako www-data – proto musí mít web-data právo do těchto
# míst zapisovat i po instalaci, ne jen v tuhle chvíli.
chown www-data:www-data /etc/supervisor/conf.d

ok "Oprávnění nastavena."

# ---------------------------------------------------------------------------
# 8) sudoers – appka potřebuje ovládat supervisorctl jako www-data
# ---------------------------------------------------------------------------

log "Nastavuji sudo pravidlo pro supervisorctl..."
SUDOERS_FILE="/etc/sudoers.d/bambups"
SUDOERS_TMP="$(mktemp)"
echo "www-data ALL=(root) NOPASSWD: /usr/bin/supervisorctl" > "$SUDOERS_TMP"
if ! visudo -c -f "$SUDOERS_TMP" >/dev/null; then
    rm -f "$SUDOERS_TMP"
    die "Vygenerovaný sudoers soubor je neplatný, nic jsem neinstaloval do /etc/sudoers.d."
fi
mv "$SUDOERS_TMP" "$SUDOERS_FILE"
chown root:root "$SUDOERS_FILE"
chmod 440 "$SUDOERS_FILE"

ok "sudo pravidlo nastaveno."

# ---------------------------------------------------------------------------
# 9) Supervisor programy (mqtt listener, go2rtc) – vypnuté, appka je
#    zapíná sama přes web podle toho, jaké moduly si uživatel aktivuje
# ---------------------------------------------------------------------------

log "Vytvářím Supervisor programy..."

cat > /etc/supervisor/conf.d/bambups-mqtt.conf <<CONF
[program:bambups-mqtt]
command=php ${APP_DIR}/artisan mqtt:listen
directory=${APP_DIR}
user=www-data
autostart=false
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/log/bambups-mqtt.log
stdout_logfile_maxbytes=5MB
stdout_logfile_backups=2
startsecs=3
startretries=5
CONF

cat > /etc/supervisor/conf.d/bambups-go2rtc.conf <<CONF
[program:bambups-go2rtc]
command=${GO2RTC_DIR}/go2rtc -config ${GO2RTC_DIR}/go2rtc.yaml
directory=${GO2RTC_DIR}
user=www-data
autostart=false
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/log/bambups-go2rtc.log
stdout_logfile_maxbytes=5MB
stdout_logfile_backups=2
startsecs=3
startretries=5
CONF

systemctl enable --now supervisor >/dev/null
supervisorctl reread >/dev/null
supervisorctl update >/dev/null

ok "Supervisor programy vytvořeny (moduly zůstávají vypnuté, dokud je nezapneš v appce)."

# ---------------------------------------------------------------------------
# 10) Self-signed SSL certifikát
# ---------------------------------------------------------------------------

log "Generuji self-signed SSL certifikát pro ${SERVER_ADDRESS}..."
SSL_DIR="/etc/nginx/ssl/bambups"
mkdir -p "$SSL_DIR"

if is_ip "$SERVER_ADDRESS"; then
    SAN="IP:${SERVER_ADDRESS}"
else
    SAN="DNS:${SERVER_ADDRESS}"
fi

openssl req -x509 -nodes -newkey rsa:2048 -days 3650 \
    -keyout "${SSL_DIR}/bambups.key" \
    -out "${SSL_DIR}/bambups.crt" \
    -subj "/CN=${SERVER_ADDRESS}" \
    -addext "subjectAltName=${SAN}" >/dev/null 2>&1

chmod 600 "${SSL_DIR}/bambups.key"

ok "SSL certifikát vygenerován (self-signed – prohlížeč při prvním vstupu ukáže varování, to je v pořádku, jen musíš potvrdit výjimku)."

# ---------------------------------------------------------------------------
# 11) nginx
# ---------------------------------------------------------------------------

log "Nastavuji nginx..."

cat > /etc/nginx/sites-available/bambups.conf <<CONF
server {
    listen ${HTTPS_PORT} ssl;
    listen [::]:${HTTPS_PORT} ssl;
    server_name ${SERVER_ADDRESS};

    root ${APP_DIR}/public;
    index index.php;

    ssl_certificate     ${SSL_DIR}/bambups.crt;
    ssl_certificate_key ${SSL_DIR}/bambups.key;
    ssl_protocols TLSv1.2 TLSv1.3;

    client_max_body_size 512M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # go2rtc – živý stream a snímky z kamer, proxované appkou přes stejný port
    location /go2rtc/ {
        proxy_pass http://127.0.0.1:1984/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
CONF

rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/bambups.conf /etc/nginx/sites-enabled/bambups.conf

nginx -t
systemctl enable --now "php${PHP_VERSION}-fpm" >/dev/null
systemctl reload nginx || systemctl restart nginx

ok "nginx nastaven a běží."

# ---------------------------------------------------------------------------
# 12) Volitelně otevřít port ve firewallu (pokud je ufw aktivní)
# ---------------------------------------------------------------------------

if command -v ufw >/dev/null 2>&1 && ufw status | grep -q "Status: active"; then
    log "Detekován aktivní ufw, otevírám port ${HTTPS_PORT}/tcp..."
    ufw allow "${HTTPS_PORT}/tcp" >/dev/null || warn "Nepodařilo se automaticky otevřít port v ufw, otevři ho prosím ručně."
fi

# ---------------------------------------------------------------------------
# 13) Uložení instalačních údajů + první admin účet
# ---------------------------------------------------------------------------

cat > "$INFO_FILE" <<INFO
BambuPS – instalační údaje (vygenerováno $(date '+%Y-%m-%d %H:%M:%S'))
================================================================

Appka:          https://${SERVER_ADDRESS}:${HTTPS_PORT}
Verze appky:     ${LATEST_TAG}
Instalátor:      v${INSTALLER_VERSION}

Databáze:
  Host:          127.0.0.1:3306
  Jméno:         ${DB_NAME}
  Uživatel:      ${DB_USER}
  Heslo:         ${DB_PASS}

Cesty:
  Appka:         ${APP_DIR}
  go2rtc:        ${GO2RTC_DIR}
  .env:          ${APP_DIR}/.env
  SSL cert:      ${SSL_DIR}

Tenhle soubor obsahuje citlivé údaje (heslo do databáze) – nikam ho neposílej,
nesdílej. Čitelný je jen pro root (chmod 600).
INFO
chmod 600 "$INFO_FILE"

echo
echo -e "${c_bold}${c_green}=== Systém je připraven, appka nastavena ===${c_reset}"
echo -e "Appka poběží na: ${c_bold}https://${SERVER_ADDRESS}:${HTTPS_PORT}${c_reset}"
echo -e "Instalační údaje (vč. hesla do DB) jsou uložené v: ${INFO_FILE}"
echo
echo "Teď vytvoříme první administrátorský účet appky – tím se přihlásíš do webu."
echo

sudo -u www-data php "${APP_DIR}/artisan" bambups:create-admin

echo
ok "Hotovo! Appka je nainstalovaná a admin účet vytvořený."
echo -e "Otevři ${c_bold}https://${SERVER_ADDRESS}:${HTTPS_PORT}${c_reset} a přihlas se."
echo "Prohlížeč u self-signed certifikátu ukáže bezpečnostní varování – to je v pořádku, potvrď výjimku a pokračuj."
echo "Přidání tiskáren, zapnutí kamery/MQTT/notifikací už proběhne přímo přes appku."
echo
