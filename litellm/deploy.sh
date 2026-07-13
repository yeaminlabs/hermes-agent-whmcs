#!/bin/bash
# ═══════════════════════════════════════════════════════════════
#  SNBD Host — LiteLLM Gateway Deployment
#  Routes customers through AWS Bedrock (no keys in containers)
# ═══════════════════════════════════════════════════════════════
set -e

LITELLM_DIR="/opt/litellm"

echo "=== Deploying LiteLLM Gateway with AWS Bedrock ==="

# 1. Create directory
mkdir -p $LITELLM_DIR/data

# 2. Create .env
cat > $LITELLM_DIR/.env << 'ENVEOF'
# ═══════════════════════════════════════════════════════════════
#  AWS Bedrock — IAM credentials (NEVER exposed to customers)
# ═══════════════════════════════════════════════════════════════
# Option A: Static IAM keys (if not on EC2)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_REGION=us-east-1

# Option B: If this server IS on EC2, leave keys blank and
# attach an IAM role with bedrock:InvokeModel permissions.
# LiteLLM picks it up automatically from the instance metadata.

# LiteLLM Master Key (for admin API + virtual key creation)
LITELLM_MASTER_KEY=sk-litellm-master-3VjXMRrqb73t4xnFFNuF1
LITELLM_SALT_KEY=sk-litellm-salt-9e7da0bd27ca6c661e
ENVEOF
chmod 600 $LITELLM_DIR/.env

# 3. Create config.yaml
cat > $LITELLM_DIR/config.yaml << 'CONFIGEOF'
# ═══════════════════════════════════════════════════════════════
#  LiteLLM Gateway — SNBD Host (AWS Bedrock Backend)
#  Customers point Hermes Agent to: http://SERVER_IP:4000/v1
#  AWS credentials stay HERE, never on customer VPS.
# ═══════════════════════════════════════════════════════════════

general_settings:
  master_key: "sk-litellm-master-3VjXMRrqb73t4xnFFNuF1"
  database_url: "sqlite:////app/data/litellm.db"
  store_model_in_db: True
  allow_user_auth: False

litellm_settings:
  drop_params: True
  set_verbose: False
  request_timeout: 600
  num_retries: 2
  cache: False

# ═══════════════════════════════════════════════════════════════
# MODELS — All routed through AWS Bedrock
# ═══════════════════════════════════════════════════════════════
#  Keys are read from env vars / IAM role automatically.
#  No keys leave this server — customers only see the proxy.
# ═══════════════════════════════════════════════════════════════

model_list:
  # ── Claude on Bedrock (for free / cheap tier) ─────────────
  - model_name: claude-sonnet
    litellm_params:
      model: bedrock/us.anthropic.claude-3-5-sonnet-20241022-v2:0
      aws_region_name: us-east-1
      rpm: 60
      max_tokens: 8192
    model_info:
      mode: completion
      supports_function_calling: true
      access_groups: ["free-tier", "paid-tier"]
      description: "Claude 3.5 Sonnet via AWS Bedrock"

  - model_name: claude-haiku
    litellm_params:
      model: bedrock/us.anthropic.claude-3-5-haiku-20241022-v1:0
      aws_region_name: us-east-1
      rpm: 100
      max_tokens: 8192
    model_info:
      mode: completion
      supports_function_calling: true
      access_groups: ["free-tier"]
      description: "Claude 3.5 Haiku via AWS Bedrock — cheapest, fastest"

  # ── Llama on Bedrock ─────────────────────────────────────
  - model_name: llama-3-8b
    litellm_params:
      model: bedrock/us.meta.llama3-2-8b-instruct-v1:0
      aws_region_name: us-east-1
      rpm: 100
      max_tokens: 8192
    model_info:
      mode: completion
      access_groups: ["free-tier"]
      description: "Llama 3.2 8B via AWS Bedrock — free-tier workhorse"

  - model_name: llama-3-70b
    litellm_params:
      model: bedrock/us.meta.llama3-2-90b-instruct-v1:0
      aws_region_name: us-east-1
      rpm: 40
      max_tokens: 8192
    model_info:
      mode: completion
      supports_function_calling: true
      access_groups: ["paid-tier"]
      description: "Llama 3.2 90B via AWS Bedrock"

  # ── Mistral on Bedrock ───────────────────────────────────
  - model_name: mistral-7b
    litellm_params:
      model: bedrock/mistral.mistral-7b-instruct-v0:2
      aws_region_name: us-east-1
      rpm: 100
      max_tokens: 8192
    model_info:
      mode: completion
      access_groups: ["free-tier"]
      description: "Mistral 7B via AWS Bedrock"

  - model_name: mistral-large
    litellm_params:
      model: bedrock/mistral.mistral-large-2402-v1:0
      aws_region_name: us-east-1
      rpm: 30
      max_tokens: 8192
    model_info:
      mode: completion
      supports_function_calling: true
      access_groups: ["paid-tier"]
      description: "Mistral Large via AWS Bedrock"

# ═══════════════════════════════════════════════════════════════
# FALLBACK & ROUTING
# ═══════════════════════════════════════════════════════════════

router_settings:
  routing_strategy: "latency-based"
  allowed_fails: 3
  num_retries: 2
  fallback_strategy: "next-best"
  redis_host: null
  redis_port: 6379
  redis_password: null
CONFIGEOF

# 4. Create docker-compose.yml
cat > $LITELLM_DIR/docker-compose.yml << 'DOCKEREOF'
version: "3.9"

services:
  litellm:
    image: ghcr.io/berriai/litellm:main-latest
    container_name: litellm-proxy
    restart: unless-stopped
    ports:
      - "4000:4000"
    volumes:
      - ./config.yaml:/app/config.yaml
      - ./data:/app/data
      - ~/.aws:/root/.aws:ro   # Mount AWS credentials if using ~/.aws/
    environment:
      - LITELLM_MASTER_KEY=sk-litellm-master-3VjXMRrqb73t4xnFFNuF1
      - LITELLM_SALT_KEY=sk-litellm-salt-9e7da0bd27ca6c661e
      - DATABASE_URL=sqlite:////app/data/litellm.db
      - STORE_MODEL_IN_DB=True
      - COOLDOWN=30
      - MAX_BUDGET=1000
    env_file:
      - .env
    command:
      - "--port"
      - "4000"
      - "--config"
      - "/app/config.yaml"
DOCKEREOF

# 5. Start it
echo "=== Starting LiteLLM ==="
cd $LITELLM_DIR && docker compose pull && docker compose up -d

# 6. Wait for health
echo "=== Waiting for LiteLLM to be healthy ==="
for i in $(seq 1 12); do
  if curl -sf http://localhost:4000/health/readiness > /dev/null 2>&1; then
    echo "✅ LiteLLM is healthy!"
    curl -s http://localhost:4000/v1/models | python3 -m json.tool 2>/dev/null | head -20
    break
  fi
  echo "  Attempt $i/12... waiting 5s"
  sleep 5
done

echo ""
echo "═══════════════════════════════════════════════════════════"
echo "  LiteLLM Gateway Deployed!"
echo "  Proxy URL: http://$(curl -s ifconfig.me):4000/v1"
echo "  Admin UI:  http://localhost:4000"
echo "  Master Key: sk-litellm-master-3VjXMRrqb73t4xnFFNuF1"
echo ""
echo "  To create a customer virtual key:"
echo "    curl -X POST http://localhost:4000/key/generate \\"
echo "      -H \"Authorization: Bearer sk-litellm-master-...\" \\"
echo "      -H \"Content-Type: application/json\" \\"
echo '      -d '"'"'{"models": ["claude-haiku"], "max_budget": 5.0, "metadata": {"customer": "name"}}'"'"''
echo "═══════════════════════════════════════════════════════════"
