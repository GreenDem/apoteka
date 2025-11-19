
create database if not exists apoteka;
use apoteka;

create user if not exists 'apoteker'@'localhost' identified by 'admin123';
grant all privileges on apoteka.* to 'apoteker'@'localhost';        
flush privileges;


CREATE TABLE `users` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`username` VARCHAR(50) NOT NULL DEFAULT 'Client',
	`password_hash` VARCHAR(255) NOT NULL,
	`roles` TEXT NULL,
	`created_at` TIMESTAMP NULL DEFAULT current_timestamp(),
	`email` VARCHAR(255) NULL DEFAULT NULL,
	`deleted_at` TIMESTAMP NULL DEFAULT NULL,
	`updated_at` TIMESTAMP NULL DEFAULT NULL,
	`last_login_at` TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY (`id`) USING BTREE,
	UNIQUE INDEX `username` (`username`) USING BTREE,
	UNIQUE INDEX `email` (`email`) USING BTREE
);

create table if not exists orders (
    id int auto_increment primary key,
    user_id int not null,
    order_date timestamp default current_timestamp,
    total_amount decimal(10, 2) not null,
    status enum('pending', 'completed', 'canceled') default 'pending',
    foreign key (user_id) references users(id) on delete restrict on update cascade,
    index idx_user_id (user_id)
);

create table if not exists products (
    id int auto_increment primary key,
    name varchar(100) not null,
    description text,
    price decimal(10, 2) not null,
    stock int not null default 0,
    img varchar(255) not null,
    updated_at timestamp default current_timestamp on update current_timestamp,
    created_at timestamp default current_timestamp,
    index idx_name (name)
);

create table if not exists order_items (
    id int auto_increment primary key,
    order_id int not null,
    product_id int not null,
    quantity int not null,
    price decimal(10, 2) not null,
    foreign key (order_id) references orders(id) on delete cascade on update cascade,
    foreign key (product_id) references products(id) on delete restrict on update cascade,
    index idx_order_id (order_id),
    index idx_product_id (product_id)
);  
