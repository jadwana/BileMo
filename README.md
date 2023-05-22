# BileMo
This project is a part of my training with Openclassrooms : Application's developper - PHP/Symfony.

## Features

Expose APIs so that applications on other web platforms can perform operations:
 - consult the list of BileMo products
 - consult the details of a BileMo product
 - consult the list of registered users linked to a client
 - consult the details of a registered user linked to a client
 - add a new user linked to a customer
 - delete a user added by a customer


### Specs
*	PHP 8
*	Symfony 6

#### Required UML diagrams
*	Use case diagrams
*	Class diagram
*	Sequence diagrams

### Requirements

*	You need to have composer on your computer
*	Your server needs PHP version 8.0
*	MySQL or MariaDB
*	Apache or Nginx

## Set up your environment
If you would like to install this project on your computer, you will first need to clone or download the repo of this project in a folder of your local server.
### Database configuration and access
1 Update DATABASE_URL .env file with your database configuration. 
  ex : DATABASE_URL=mysql://db_user:db_password@127.0.0.1:3306/db_name

2 Create database : symfony console doctrine:database:create

3 Create database structure : symfony console doctrine:schema:update --force

4 Insert fictive data : symfony console doctrine:fixtures:load

### Generate your keys for using JWT Token
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096

openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem-pubout

### Configure JWT PASSPHRASE in .env file
ex : JWT_PASSPHRASE='xxx'

## Start the project
symfony server:start

## Usage
If you use fictive data, you can login with following account (which is a admin account) :

* username : admin@mail.com
* paswword : password

Or with this account (wich is a customer account)

* username : customer1@mail.com
* paswword : password

Attention : You must generate a token befor using API

### Congratulations, you can now test your API

To view the online documentation and test the API go to the following address in your browser:
http://127.0.0.1:8000/api/doc
(you can also test with postman)

