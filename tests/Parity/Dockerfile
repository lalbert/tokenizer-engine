# Elasticsearch node used by the parity tests (tests/Parity).
ARG ES_VERSION=9.5.5
FROM elasticsearch:${ES_VERSION}
RUN bin/elasticsearch-plugin install --batch analysis-icu analysis-phonetic
