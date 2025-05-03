#!/bin/bash

set -e 

alias gpg2="docker run -it --rm --name gpg2 -v kernel-compiler-keyring:/root/.gnupg gpg2"

gpg2 --keyserver keyserver.ubuntu.com --recv-keys 647F28654894E3BD457199BE38DBBDC86092693E
gpg2 --tofu-policy good 647F28654894E3BD457199BE38DBBDC86092693E

gpg2 --keyserver pgp.mit.edu --recv C77B9667
gpg2 --tofu-policy good C77B9667

gpg2 --keyserver pgp.mit.edu --recv D4598027
gpg2 --tofu-policy good D4598027
