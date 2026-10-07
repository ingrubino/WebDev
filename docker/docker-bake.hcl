# Build multi-architettura con buildx bake. Da lanciare nella cartella docker/.
#
#   docker buildx bake -f docker-bake.hcl --allow=fs.read=.. --push
#   PLATFORMS=linux/arm64 docker buildx bake -f docker-bake.hcl --allow=fs.read=.. --load
#
# --allow=fs.read=.. autorizza buildx a leggere la cartella WebDev (contesto di build).

variable "IMAGE_PREFIX" { default = "webdev" }
variable "TAG"          { default = "latest" }
variable "PLATFORMS"    { default = "linux/amd64,linux/arm64,linux/arm/v7" }

group "default" {
  targets = ["web", "api"]
}

target "web" {
  context    = ".."
  dockerfile = "docker/frontend.Dockerfile"
  platforms  = split(",", PLATFORMS)
  tags       = ["${IMAGE_PREFIX}/web:${TAG}"]
}

target "api" {
  context    = "../vue"
  dockerfile = "api/Dockerfile"
  platforms  = split(",", PLATFORMS)
  tags       = ["${IMAGE_PREFIX}/api:${TAG}"]
}
