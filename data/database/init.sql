
create database if not exists apoteka;
use apoteka;

create user if not exists 'apoteker'@'localhost' identified by 'admin123';
grant all privileges on apoteka.* to 'apoteker'@'localhost';        
flush privileges;


CREATE TABLE `users` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`username` VARCHAR(50) NOT NULL DEFAULT 'Client' COLLATE 'utf8mb4_uca1400_ai_ci',
	`password_hash` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_uca1400_ai_ci',
	`roles` VARCHAR(50) NOT NULL DEFAULT '' COLLATE 'utf8mb4_uca1400_ai_ci',
	`created_at` TIMESTAMP NULL DEFAULT current_timestamp(),
	`email` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_uca1400_ai_ci',
	`deleted_at` TIMESTAMP NULL DEFAULT NULL,
	`updated_at` TIMESTAMP NULL DEFAULT NULL,
	`last_login_at` TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY (`id`) USING BTREE,
	UNIQUE INDEX `username` (`username`) USING BTREE
)
COLLATE='utf8mb4_uca1400_ai_ci'
ENGINE=InnoDB
;


create table if not exists orders (
    id int auto_increment primary key,
    user_id int not null,
    order_date timestamp default current_timestamp,
    total_amount decimal(10, 2) not null,
    status enum('pending', 'completed', 'canceled') default 'pending',
    foreign key (user_id) references users(id)
);

create table if not exists products (
    id int auto_increment primary key,
    name varchar(100) not null,
    description text,
    price decimal(10, 2) not null,
    stock int not null,
    created_at timestamp default current_timestamp
);

create table if not exists order_items (
    id int auto_increment primary key,
    order_id int not null,
    product_id int not null,
    quantity int not null,
    price decimal(10, 2) not null,
    foreign key (order_id) references orders(id),
    foreign key (product_id) references products(id)
);  
