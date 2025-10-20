sudo apt-get update -y
sudo apt-get install curl sqlite3 -y

sudo systemctl stop sonarr

if ! getent group media >/dev/null; then
    sudo groupadd media
fi

if ! getent passwd sonarr >/dev/null; then
    sudo adduser --system --no-create-home --ingroup media sonarr
    sleep 3
fi

if ! getent group media | grep -qw sonarr; then
    sudo usermod -a -G media sonarr
    sleep 3
fi

sudo mkdir -p /var/lib/sonarr
sudo chown -R sonarr:media /var/lib/sonarr
sudo chmod 775 /var/lib/sonarr

echo ""
ARCH=$(dpkg --print-architecture)

dlbase="https://services.sonarr.tv/v1/download/{{ $branch }}/latest?version=4&os=linux"
case "$ARCH" in
"amd64") DLURL="${dlbase}&arch=x64" ;;
"armhf") DLURL="${dlbase}&arch=arm" ;;
"arm64") DLURL="${dlbase}&arch=arm64" ;;
*)
echo -e "Arch is not supported!"
exit 1
;;
esac

sudo wget --content-disposition "$DLURL"
sudo tar -xvzf Sonarr*.linux*.tar.gz >/dev/null 2>&1
sudo rm -rf /opt/Sonarr
sudo mv Sonarr /opt/
sudo chown sonarr:media -R /opt/Sonarr
sudo chmod 775 /opt/Sonarr

sudo rm -rf /etc/systemd/system/sonarr.service

cat << EOF | sudo tee /etc/systemd/system/sonarr.service > /dev/null
[Unit]
Description=Sonarr Daemon
After=syslog.target network.target
[Service]
User=sonarr
Group=media
UMask=0002
Type=simple

ExecStart=/opt/Sonarr/Sonarr -nobrowser -data=/var/lib/sonarr/
TimeoutStopSec=20
KillMode=process
Restart=on-failure
[Install]
WantedBy=multi-user.target
EOF

sudo systemctl -q daemon-reload
sudo systemctl enable --now -q sonarr

sudo rm Sonarr*.linux*.tar.gz

sudo mkdir -p /home/vito/media
sudo mkdir -p /home/vito/media/tv
sudo chown vito:media /home/vito/media
sudo chown vito:media /home/vito/media/tv
sudo chmod 775 /home/vito/media
sudo chmod 775 /home/vito/media/tv
