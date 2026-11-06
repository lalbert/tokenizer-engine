# Elasticsearch parity tests

These tests send the same texts to this library and to a real
Elasticsearch node (`_analyze` API), and compare terms, positions and
offsets for every case of `ParityCases.php`.

```bash
docker build -t tokenizer-engine-es tests/Parity
docker run -d --name tokenizer-engine-es -p 9200:9200 \
  -e discovery.type=single-node -e xpack.security.enabled=false \
  -e ES_JAVA_OPTS="-Xms512m -Xmx512m" tokenizer-engine-es
ELASTICSEARCH_URL=http://localhost:9200 composer parity
```

Use `--build-arg ES_VERSION=8.19.23` to test another Elasticsearch version.
Without `ELASTICSEARCH_URL` the suite is skipped.
