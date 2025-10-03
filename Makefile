.PHONY: build zip clean

build:
	@npm ci
	@npm run build
	@composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

zip: build
	@mkdir -p artifact
	@zip -r artifact/sg-jobs-build.zip . -x ".git/*" ".github/*" "node_modules/*" "tests/*" "**/*.map" "artifact/*"

clean:
	@rm -rf vendor node_modules artifact
