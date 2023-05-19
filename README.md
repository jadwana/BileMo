# BileMo
Projet 7
## Features

The website includes the following pages :

* Homepage : display a list of all tricks. Each trick must include its name which is the link to the trick detail, a link to modify or delete this trick (only for connected user). And a link to add a new trick.
* One trick : individual trick page with the trick name, featured picture, description of the trick headline,created at date, updated at date, comments & publish comment form (only for connected user)
* Tricks management : add, modify or delete a trick
* Profile page : user informations and link to change avatar and password
*	Register / log forms
* Reset password page
* Navbar & footer present on all pages.

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
1 Update DATABASE_URL .env file with your database configuration. ex : DATABASE_URL=mysql://db_user:db_password@127.0.0.1:3306/db_name

2 Create database : symfony console doctrine:database:create

3 Create database structure : symfony console doctrine:migration:migrate

4 Insert fictive data(optional) : symfony console doctrine:fixtures:load

### Configure MAILER_DSN of Symfony mailer in .env file
ex : MAILER_DSN=smtp://localhost:1025 if you want to use MailHog

### Configure JWT Secret in .env file
ex : JWT_SECRET='xxx'

## Start the project
symfony server:start

## Usage
If you use fictive data, you can login with following account (which is a admin account) :

* username : Jadwana
* paswword : admin01

If you did not use fictive data:

* Create a user account with sign up form
* Activate your account by following the activation link

### Congratulations, the SnowTricks project is now accessible at: localhost:8000
