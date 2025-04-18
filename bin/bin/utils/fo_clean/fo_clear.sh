#!/bin/bash

./utils/fo_clean/fo_config.sh &
./utils/fo_clean/fo_db.sh &
./utils/fo_clean/fo_repo.sh &
./utils/fo_clean/fo_user.sh &

wait
