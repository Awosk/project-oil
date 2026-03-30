#!/bin/bash

# --- Ayarlar ---
SERVICE_NAME="project-oil-mail-worker"
SERVICE_FILE="/etc/systemd/system/$SERVICE_NAME.service"
SCRIPT_PATH=$(readlink -f "$0")
WORKING_DIR=$(dirname "$SCRIPT_PATH")
PHP_BIN=$(which php)
PHP_SCRIPT="$WORKING_DIR/mail_worker.php"
LOG_FILE="$WORKING_DIR/mail_worker.log"

# --- Fonksiyonlar ---

install_service() {
    echo "Servis kuruluyor: $SERVICE_NAME"
    
    # Root kontrolü
    if [ "$EUID" -ne 0 ]; then 
        echo "HATA: Servis kurulumu için sudo yetkisi gerekiyor!"
        exit 1
    fi

    # Servis dosyası oluşturma
    cat <<EOF > $SERVICE_FILE
[Unit]
Description=Project Oil Mail Worker Service
After=network.target

[Service]
Type=simple
User=$USER
Group=$USER
WorkingDirectory=$WORKING_DIR
ExecStart=$SCRIPT_PATH run
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

    systemctl daemon-reload
    systemctl enable $SERVICE_NAME
    systemctl start $SERVICE_NAME
    
    echo "Başarılı: Servis kuruldu ve başlatıldı."
    echo "Durum kontrolü: systemctl status $SERVICE_NAME"
}

uninstall_service() {
    echo "Servis kaldırılıyor: $SERVICE_NAME"
    
    if [ "$EUID" -ne 0 ]; then 
        echo "HATA: Servis kaldırmak için sudo yetkisi gerekiyor!"
        exit 1
    fi

    systemctl stop $SERVICE_NAME
    systemctl disable $SERVICE_NAME
    rm -f $SERVICE_FILE
    systemctl daemon-reload
    
    echo "Başarılı: Servis sistemden temizlendi."
}

run_worker() {
    echo "Mail worker döngüsü başlatıldı... Log: $LOG_FILE"
    while true
    do
        $PHP_BIN "$PHP_SCRIPT" >> "$LOG_FILE" 2>&1
        sleep 60
    done
}

# --- Ana Akış ---

case "$1" in
    install)
        install_service
        ;;
    uninstall)
        uninstall_service
        ;;
    run)
        run_worker
        ;;
    *)
        echo "Kullanım: $0 {install|uninstall|run}"
        echo "  install   : Servisi sisteme kurar ve başlatır."
        echo "  uninstall : Servisi durdurur ve siler."
        echo "  run       : Scripti manuel (döngüde) çalıştırır."
        exit 1
        ;;
esac