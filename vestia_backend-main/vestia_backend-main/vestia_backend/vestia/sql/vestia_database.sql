-- ============================================================
-- VESTIA COUTURE — Database Schema
-- Compatible with PostgreSQL 12+
-- ============================================================

CREATE TABLE admins (
  id         SERIAL PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  email      VARCHAR(150) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO admins (name, email, password) VALUES
('Admin Vestia', 'admin@vestia.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

CREATE TABLE users (
  id         SERIAL PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  phone      VARCHAR(20) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  avatar     VARCHAR(500) DEFAULT NULL,
  is_active  SMALLINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE OR REPLACE FUNCTION update_users_timestamp()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = CURRENT_TIMESTAMP;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER users_updated_at BEFORE UPDATE ON users
FOR EACH ROW EXECUTE FUNCTION update_users_timestamp();

CREATE TABLE auth_tokens (
  id         SERIAL PRIMARY KEY,
  user_id    INTEGER NOT NULL,
  token      VARCHAR(64) NOT NULL UNIQUE,
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE categories (
  id         SERIAL PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  name_ar    VARCHAR(100) DEFAULT NULL,
  name_fr    VARCHAR(100) DEFAULT NULL,
  slug       VARCHAR(120) NOT NULL UNIQUE,
  sort_order INTEGER DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO categories (name, name_ar, name_fr, slug, sort_order) VALUES
('All',     'الكل',      'Tous',       'all',     0),
('Tshirts', 'تيشيرتات',  'T-shirts',   'tshirts', 1),
('Jeans',   'جينز',      'Jeans',      'jeans',   2),
('Shoes',   'أحذية',     'Chaussures', 'shoes',   3),
('Jackets', 'جاكيتات',   'Vestes',     'jackets', 4);

CREATE TABLE products (
  id          SERIAL PRIMARY KEY,
  category_id INTEGER DEFAULT NULL,
  name        VARCHAR(200) NOT NULL,
  name_ar     VARCHAR(200) DEFAULT NULL,
  name_fr     VARCHAR(200) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  price       NUMERIC(10,2) NOT NULL,
  old_price   NUMERIC(10,2) DEFAULT NULL,
  image_url   VARCHAR(500) DEFAULT NULL,
  sizes       VARCHAR(100) DEFAULT 'S,M,L,XL,XXL',
  is_active   SMALLINT DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE OR REPLACE FUNCTION update_products_timestamp()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = CURRENT_TIMESTAMP;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER products_updated_at BEFORE UPDATE ON products
FOR EACH ROW EXECUTE FUNCTION update_products_timestamp();

INSERT INTO products (category_id, name, name_ar, name_fr, description, price, old_price, image_url) VALUES
(2, 'Regular Fit Slogan', 'تيشيرت فيت عادي شعار', 'T-shirt Fit Régulier Slogan', 'The name says it all, the right size slightly snugs the body leaving enough room for comfort in the sleeves and waist.', 1190.00, NULL, 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=400&h=480&fit=crop&auto=format'),
(2, 'Regular Fit Polo', 'تيشيرت فيت عادي بولو', 'T-shirt Fit Régulier Polo', 'The name says it all, the right size slightly snugs the body leaving enough room for comfort in the sleeves and waist.', 1100.00, 2291.00, 'https://images.unsplash.com/photo-1586790170083-2f9ceadc732d?w=400&h=480&fit=crop&auto=format'),
(2, 'Regular Fit Black', 'تيشيرت فيت عادي أسود', 'T-shirt Fit Régulier Noir', 'The name says it all, the right size slightly snugs the body leaving enough room for comfort in the sleeves and waist.', 1690.00, NULL, 'https://images.unsplash.com/photo-1503341455253-b2e723bb3dbb?w=400&h=480&fit=crop&auto=format'),
(2, 'Regular Fit V-Neck', 'تيشيرت فيت عادي V-Neck', 'T-shirt Fit Régulier Col V', 'The name says it all, the right size slightly snugs the body leaving enough room for comfort in the sleeves and waist.', 1290.00, NULL, 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?w=400&h=480&fit=crop&auto=format'),
(2, 'Regular Fit Slogan Pink', 'تيشيرت فيت عادي شعار وردي', 'T-shirt Fit Régulier Slogan Rose', 'The name says it all, the right size slightly snugs the body leaving enough room for comfort in the sleeves and waist.', 1190.00, NULL, 'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?w=400&h=480&fit=crop&auto=format'),
(2, 'Regular Fit Striped', 'تيشيرت فيت عادي مخطط', 'T-shirt Fit Régulier Rayé', 'The name says it all, the right size slightly snugs the body leaving enough room for comfort in the sleeves and waist.', 1190.00, NULL, 'https://images.unsplash.com/photo-1556905055-8f358a7a47b2?w=400&h=480&fit=crop&auto=format'),
(2, 'Regular Fit Crew', 'تيشيرت فيت عادي كرو', 'T-shirt Fit Régulier Crew', 'The name says it all, the right size slightly snugs the body leaving enough room for comfort in the sleeves and waist.', 990.00, NULL, 'https://images.unsplash.com/photo-1576566588028-4147f3842f27?w=400&h=480&fit=crop&auto=format'),
(2, 'Regular Fit Striped Blue', 'تيشيرت فيت عادي مخطط أزرق', 'T-shirt Fit Régulier Rayé Bleu', 'The name says it all, the right size slightly snugs the body leaving enough room for comfort in the sleeves and waist.', 1390.00, NULL, 'https://images.unsplash.com/photo-1571945153237-4929e783af4a?w=400&h=480&fit=crop&auto=format');

CREATE TABLE saved_items (
  id         SERIAL PRIMARY KEY,
  user_id    INTEGER NOT NULL,
  product_id INTEGER NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (user_id, product_id),
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE cart_items (
  id         SERIAL PRIMARY KEY,
  user_id    INTEGER NOT NULL,
  product_id INTEGER NOT NULL,
  quantity   INTEGER DEFAULT 1,
  size       VARCHAR(10) DEFAULT 'M',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (user_id, product_id, size),
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE OR REPLACE FUNCTION update_cart_items_timestamp()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = CURRENT_TIMESTAMP;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER cart_items_updated_at BEFORE UPDATE ON cart_items
FOR EACH ROW EXECUTE FUNCTION update_cart_items_timestamp();

CREATE TYPE order_status AS ENUM ('Packing', 'Picked', 'In Transit', 'Completed', 'Cancelled');

CREATE TABLE orders (
  id           SERIAL PRIMARY KEY,
  user_id      INTEGER NOT NULL,
  status       order_status DEFAULT 'Packing',
  subtotal     NUMERIC(10,2) NOT NULL,
  shipping_fee NUMERIC(10,2) NOT NULL DEFAULT 80.00,
  vat          NUMERIC(10,2) NOT NULL DEFAULT 0.00,
  total        NUMERIC(10,2) NOT NULL,
  notes        TEXT DEFAULT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE OR REPLACE FUNCTION update_orders_timestamp()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = CURRENT_TIMESTAMP;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER orders_updated_at BEFORE UPDATE ON orders
FOR EACH ROW EXECUTE FUNCTION update_orders_timestamp();

CREATE TABLE order_items (
  id         SERIAL PRIMARY KEY,
  order_id   INTEGER NOT NULL,
  product_id INTEGER DEFAULT NULL,
  name       VARCHAR(200) NOT NULL,
  image_url  VARCHAR(500) DEFAULT NULL,
  price      NUMERIC(10,2) NOT NULL,
  quantity   INTEGER NOT NULL DEFAULT 1,
  size       VARCHAR(10) DEFAULT 'M',
  FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

CREATE TABLE reviews (
  id         SERIAL PRIMARY KEY,
  user_id    INTEGER NOT NULL,
  product_id INTEGER NOT NULL,
  order_id   INTEGER DEFAULT NULL,
  rating     SMALLINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  text       TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (user_id, product_id),
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE SET NULL
);
