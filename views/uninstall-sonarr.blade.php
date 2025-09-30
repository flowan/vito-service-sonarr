sudo systemctl disable sonarr
sudo systemctl stop sonarr

sudo rm -rf /etc/systemd/system/sonarr.service
sudo rm -rf /opt/Sonarr
sudo rm -rf /var/lib/sonarr
