#!/bin/bash

# Resolve the absolute path of the module root, regardless of where the script is called from
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"
MODULE_ROOT="$(dirname "$SCRIPT_DIR")"

echo "Module root: $MODULE_ROOT"

# Step 1: Check for the vendor directory to ensure composer install was run
if [ ! -d "$MODULE_ROOT/vendor" ]; then
    echo -e "\033[31m[ERROR] The 'vendor' directory is missing in the database module.\033[0m"
    echo "Please navigate to '$MODULE_ROOT' and run 'composer install' to install PHPUnit and other testing dependencies."
    exit 1
fi

# Step 2: Start the Docker container
echo -e "\n========================================"
echo "Starting test database container..."
echo "========================================"
cd "$MODULE_ROOT/tests"
docker compose up -d

# Setup a trap to ALWAYS stop the container on script exit (whether the tests pass, fail, or the user hits Ctrl+C)
trap 'echo -e "\n========================================\nStopping test database container...\n========================================"; cd "$MODULE_ROOT/tests"; docker compose down' EXIT

# Dynamically wait for the MariaDB container to pass its healthcheck (max 30 seconds wait)
echo -n "Waiting for MariaDB to become healthy..."
for i in {1..60}; do
    STATUS=$(docker inspect -f '{{.State.Health.Status}}' $(docker compose ps -q blocks_db_test) 2>/dev/null)
    if [ "$STATUS" == "healthy" ]; then
        echo -e "\nMariaDB is up and ready!"
        break
    fi
    echo -n "."
    sleep 1
done

# Step 3: Run the integration tests
echo -e "\n========================================"
echo "Running Integration Tests..."
echo "========================================"
cd "$MODULE_ROOT"

# Run PHPUnit
./vendor/bin/phpunit tests/Integration/
TEST_RESULT=$?

# Exit with the exact status code that PHPUnit returned (0 for success, >0 for failure). 
# The trap will automatically shut down the docker container right after this.
exit $TEST_RESULT
