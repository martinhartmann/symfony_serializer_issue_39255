test:
	composer install \
		&& docker-compose down \
		&& docker-compose up --build --force-recreate
