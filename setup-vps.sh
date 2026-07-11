#!/bin/bash

# Clear screen
clear

echo "========================================================="
echo "        SNBD Host - Hermes VPS Setup & Config Tool       "
echo "========================================================="
echo ""

# 1. Get Public IP
echo "Checking Public IP..."
PUBLIC_IP=$(curl -s https://ipinfo.io/ip || curl -s https://api.ipify.org || echo "Could not detect")

# 2. Get Username
USER_NAME=$(whoami)

# 3. Check / Install Docker
echo "Checking Docker installation..."
if ! command -v docker &> /dev/null; then
    echo "Docker is not installed. Installing Docker..."
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh &> /dev/null
    rm -f get-docker.sh
    systemctl start docker
    systemctl enable docker
    echo "Docker installed successfully!"
else
    echo "Docker is already installed."
fi

# 4. Check / Install Curl
if ! command -v curl &> /dev/null; then
    echo "Installing curl..."
    if command -v apt-get &> /dev/null; then
        apt-get update && apt-get install -y curl &> /dev/null
    elif command -v yum &> /dev/null; then
        yum install -y curl &> /dev/null
    fi
fi

# 5. Setup SSH Key for WHMCS (Access Hash)
echo "Generating secure SSH key pair for WHMCS..."
SSH_DIR="$HOME/.ssh"
mkdir -p "$SSH_DIR"
chmod 700 "$SSH_DIR"

KEY_FILE="$SSH_DIR/whmcs_hermes"
rm -f "$KEY_FILE" "$KEY_FILE.pub"

# Generate 4096-bit RSA key without password
ssh-keygen -t rsa -b 4096 -f "$KEY_FILE" -N "" -q

# Append to authorized_keys
cat "$KEY_FILE.pub" >> "$SSH_DIR/authorized_keys"
chmod 600 "$SSH_DIR/authorized_keys"

# Read private key content
PRIVATE_KEY=$(cat "$KEY_FILE")

# Clean up local private/public key files from host
rm -f "$KEY_FILE" "$KEY_FILE.pub"

echo ""
echo "========================================================="
echo "              WHMCS SERVER ENTRY DETAILS                 "
echo "========================================================="
echo "Copy and paste these exact values into your WHMCS form:"
echo ""
echo "Hostname or IP Address:"
echo "----------------------"
echo "$PUBLIC_IP"
echo ""
echo "Username:"
echo "--------"
echo "$USER_NAME"
echo ""
echo "Password:"
echo "--------"
echo "(Leave blank, we are using the Access Hash)"
echo ""
echo "Access Hash:"
echo "------------"
echo "$PRIVATE_KEY"
echo "========================================================="
