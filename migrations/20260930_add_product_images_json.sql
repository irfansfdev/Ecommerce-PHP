ALTER TABLE products
    ADD COLUMN images JSON NULL AFTER image;

UPDATE products
SET images = JSON_ARRAY(image)
WHERE images IS NULL;