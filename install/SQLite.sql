-- 用户表
CREATE TABLE IF NOT EXISTS "users" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "username" TEXT NOT NULL UNIQUE,
  "password" TEXT NOT NULL,
  "email" TEXT NOT NULL UNIQUE,
  "role" TEXT NOT NULL DEFAULT 'user' CHECK ("role" IN ('admin', 'editor', 'user')),
  "created_at" TEXT NOT NULL,
  "updated_at" TEXT NULL
);

-- 分类表
CREATE TABLE IF NOT EXISTS "categories" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "name" TEXT NOT NULL,
  "slug" TEXT NOT NULL UNIQUE,
  "parent_id" INTEGER NULL,
  "description" TEXT NULL,
  "created_at" TEXT NOT NULL,
  "updated_at" TEXT NULL,
  FOREIGN KEY ("parent_id") REFERENCES "categories"("id") ON DELETE SET NULL
);

-- 产品/文章表
CREATE TABLE IF NOT EXISTS "posts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "title" TEXT NOT NULL,
  "content" TEXT NOT NULL,
  "summary" TEXT NULL,
  "link" TEXT NULL,
  "icon" TEXT NULL,
  "gallery" TEXT NULL,
  "category_id" INTEGER NULL,
  "user_id" INTEGER NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft' CHECK ("status" IN ('published', 'draft')),
  "is_top" INTEGER NOT NULL DEFAULT 0,
  "create_time" TEXT NOT NULL,
  "update_time" TEXT NULL,
  FOREIGN KEY ("category_id") REFERENCES "categories"("id") ON DELETE SET NULL,
  FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE
);



