#!/bin/bash

echo "Installing peer dependencies that require builds..."

pnpm add -D @parcel/watcher --allow-build=@parcel/watcher
pnpm add -D sharp --allow-build=sharp