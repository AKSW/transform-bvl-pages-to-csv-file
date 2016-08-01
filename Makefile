default:
	php create-files.php
	@echo ""
	@echo ""
	rm le-online-extracted-places.ttl
	@echo ""
	@echo "Transformation in fancy Turtle/RDF:"
	@echo ""
	rapper -i turtle -o turtle raw-places.ttl > le-online-extracted-places.ttl
