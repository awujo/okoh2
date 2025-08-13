CREATE TABLE `user`(
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(255) NOT NULL,
    `fullname` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `country` VARCHAR(255) NOT NULL,
    `google_id` VARCHAR(255) NOT NULL,
    `phone_number` BIGINT NOT NULL,
    `deposit_balance` BIGINT NOT NULL,
    `interest_balance` BIGINT NOT NULL,
    `referal_balance` BIGINT NOT NULL,
    `referrer_id` BIGINT NOT NULL,
    `email_is_confirmed` BOOLEAN NOT NULL,
    `2fa_is_done` BOOLEAN NOT NULL,
    `kyc_is_done` BIGINT NOT NULL,
    `is_suspended` BOOLEAN NOT NULL,
    `address` VARCHAR(255) NOT NULL,
    `state` VARCHAR(255) NOT NULL,
    `zipcode` BIGINT NOT NULL,
    `city` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `code` BIGINT NOT NULL
);
ALTER TABLE
    `user` ADD UNIQUE `user_google_id_unique`(`google_id`);
CREATE TABLE `deposit`(
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT NOT NULL,
    `transaction_id` VARCHAR(255) NOT NULL,
    `gateway` VARCHAR(255) NOT NULL,
    `amount` BIGINT NOT NULL,
    `status` VARCHAR(255) NOT NULL,
    `wallet` VARCHAR(255) NOT NULL DEFAULT 'deposit_wallet',
    `type` VARCHAR(255) NOT NULL DEFAULT 'plus',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_withdrawal_fee` BIGINT NOT NULL
);
ALTER TABLE
    `deposit` ADD INDEX `deposit_user_id_index`(`user_id`);
CREATE TABLE `investment`(
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT NOT NULL,
    `transaction_id` VARCHAR(255) NOT NULL,
    `plan` VARCHAR(255) NOT NULL,
    `amount` BIGINT NOT NULL,
    `interest` BIGINT NOT NULL,
    `days_count` BIGINT NOT NULL,
    `status` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE
    `investment` ADD INDEX `investment_user_id_index`(`user_id`);
CREATE TABLE `withdrawal`(
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT NOT NULL,
    `transaction_id` VARCHAR(255) NOT NULL,
    `amount` BIGINT NOT NULL,
    `withdrawable_amount` BIGINT NOT NULL,
    `wallet` VARCHAR(255) NOT NULL,
    `type` VARCHAR(255) NOT NULL DEFAULT 'minus',
    `wallet_address` VARCHAR(255) NOT NULL,
    `gateway` VARCHAR(255) NOT NULL,
    `status` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE
    `withdrawal` ADD INDEX `withdrawal_user_id_index`(`user_id`);
CREATE TABLE `kyc`(
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT NOT NULL,
    `status` VARCHAR(255) NOT NULL,
    `fullname` VARCHAR(255) NOT NULL,
    `nid` BIGINT NOT NULL,
    `gender` BIGINT NOT NULL,
    `country` VARCHAR(255) NOT NULL,
    `state` VARCHAR(255) NOT NULL,
    `hobby` VARCHAR(255) NOT NULL,
    `nid_url` VARCHAR(255) NOT NULL,
    `selfie_url` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE
    `kyc` ADD INDEX `kyc_user_id_index`(`user_id`);
CREATE TABLE `support_ticket`(
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT NOT NULL,
    `ticket_id` BIGINT NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `status` VARCHAR(255) NOT NULL,
    `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE
    `support_ticket` ADD INDEX `support_ticket_user_id_index`(`user_id`);
CREATE TABLE `admin`(
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL
);
ALTER TABLE
    `deposit` ADD CONSTRAINT `deposit_user_id_foreign` FOREIGN KEY(`user_id`) REFERENCES `user`(`id`);
ALTER TABLE
    `investment` ADD CONSTRAINT `investment_user_id_foreign` FOREIGN KEY(`user_id`) REFERENCES `user`(`id`);
ALTER TABLE
    `kyc` ADD CONSTRAINT `kyc_user_id_foreign` FOREIGN KEY(`user_id`) REFERENCES `user`(`id`);
ALTER TABLE
    `withdrawal` ADD CONSTRAINT `withdrawal_user_id_foreign` FOREIGN KEY(`user_id`) REFERENCES `user`(`id`);
ALTER TABLE
    `support_ticket` ADD CONSTRAINT `support_ticket_user_id_foreign` FOREIGN KEY(`user_id`) REFERENCES `user`(`id`);