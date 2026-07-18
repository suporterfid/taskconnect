.DEFAULT_GOAL := help

.PHONY: help up down bootstrap composer artisan npm test e2e release shell

help up down bootstrap composer artisan npm test e2e release shell:
	@bash scripts/tc.sh $@
