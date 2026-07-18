.DEFAULT_GOAL := help

.PHONY: help up down bootstrap composer artisan npm test e2e release deploy shell

help up down bootstrap composer artisan npm test e2e release deploy shell:
	@bash scripts/tc.sh $@
