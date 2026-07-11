#!/bin/bash

# Clear terminal screen
clear

echo "========================================================="
echo "      SNBD Host - Hermes WHMCS Module Installer          "
echo "========================================================="
echo ""

# 1. Verify WHMCS root directory
WHMCS_ROOT="."
if [ ! -f "$WHMCS_ROOT/configuration.php" ]; then
    echo "WARNING: 'configuration.php' was not found in the current directory."
    read -p "Please enter the absolute path to your WHMCS root directory (e.g. /var/www/whmcs): " WHMCS_INPUT
    
    if [ -f "$WHMCS_INPUT/configuration.php" ]; then
        WHMCS_ROOT="$WHMCS_INPUT"
    else
        echo "ERROR: Invalid WHMCS installation directory. Cannot locate configuration.php. Aborting."
        exit 1
    fi
fi

echo "WHMCS directory found at: $WHMCS_ROOT"
echo ""

# 2. Check dependencies
echo "Checking dependencies..."
if ! command -v curl &> /dev/null; then
    echo "ERROR: 'curl' is required but not installed. Please install curl and try again."
    exit 1
fi

if ! command -v unzip &> /dev/null; then
    echo "ERROR: 'unzip' is required but not installed. Please install unzip and try again."
    exit 1
fi

# 3. Downloading latest module code from GitHub
echo "Downloading module files from yeaminlabs/hermes-agent-whmcs..."
TEMP_ZIP="hermes_agent_whmcs_temp.zip"
TEMP_DIR="hermes_agent_whmcs_temp_dir"

# Clean up any leftover files
rm -f "$TEMP_ZIP"
rm -rf "$TEMP_DIR"

curl -sL "https://github.com/yeaminlabs/hermes-agent-whmcs/archive/refs/heads/main.zip" -o "$TEMP_ZIP"

if [ ! -f "$TEMP_ZIP" ]; then
    echo "ERROR: Failed to download files from GitHub. Please check your internet connection."
    exit 1
fi

# 4. Extracting files
echo "Extracting files..."
unzip -q "$TEMP_ZIP" -d "$TEMP_DIR"

# Locate the root of the extracted git archive (which has a suffix like -main)
EXTRACTED_FOLDER=$(ls -d "$TEMP_DIR"/hermes-agent-whmcs-*)

if [ -z "$EXTRACTED_FOLDER" ] || [ ! -d "$EXTRACTED_FOLDER" ]; then
    echo "ERROR: Extraction failed or files are corrupted."
    rm -f "$TEMP_ZIP"
    rm -rf "$TEMP_DIR"
    exit 1
fi

# 5. Installing files
echo "Installing Hermes Agent Provisioning Module..."
mkdir -p "$WHMCS_ROOT/modules/servers/hermesagent"
cp -r "$EXTRACTED_FOLDER/modules/servers/hermesagent/"* "$WHMCS_ROOT/modules/servers/hermesagent/"

echo "Installing Hermes Agent Manager Addon Module..."
mkdir -p "$WHMCS_ROOT/modules/addons/hermesagent"
cp -r "$EXTRACTED_FOLDER/modules/addons/hermesagent/"* "$WHMCS_ROOT/modules/addons/hermesagent/"

# 6. Cleaning up temporary files
echo "Cleaning up installer files..."
rm -f "$TEMP_ZIP"
rm -rf "$TEMP_DIR"

echo ""
echo "========================================================="
echo "            INSTALLATION COMPLETED SUCCESSFULLY          "
echo "========================================================="
echo "Next Steps:"
echo "1. Log in to your WHMCS Admin Area."
echo "2. Navigate to Setup > Addon Modules."
echo "3. Locate 'Hermes Agent Manager' and click Activate, then Configure."
echo "4. Under configuration, check Full Administrator permissions and click Save."
echo "5. Navigate to Addons > Hermes Agent Manager."
echo "6. Select your Hermes hosting product from the list and run the 'One-Click Product Setup'."
echo "This will instantly configure all the required customer fields and options."
echo "========================================================="
echo ""
