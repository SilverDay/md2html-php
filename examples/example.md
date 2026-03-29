# md2html-php Demo

Welcome to the **md2html-php** demo document. This file exercises all of the
features supported by the library.

---

## Table of Contents

- [Typography](#typography)
- [Links & Images](#links--images)
- [Code Blocks](#code-blocks)
- [Tables](#tables)
- [Blockquotes](#blockquotes)
- [Lists](#lists)

---

## Typography

Normal paragraph text with **bold**, *italic*, ***bold italic***, and
~~strikethrough~~ formatting.

Inline `code snippets` are rendered with a distinct background.

---

## Links & Images

[Visit GitHub](https://github.com "GitHub homepage")

![Sample image](https://via.placeholder.com/800x200 "Placeholder image")

---

## Code Blocks

### PHP

```php
<?php

declare(strict_types=1);

class Greeter
{
    public function __construct(private readonly string $name) {}

    public function greet(): string
    {
        return "Hello, {$this->name}!";
    }
}

$greeter = new Greeter('World');
echo $greeter->greet(); // Hello, World!
```

### JavaScript

```js
// Async fetch with error handling
async function loadData(url) {
    try {
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`HTTP error: ${response.status}`);
        }
        const data = await response.json();
        return data;
    } catch (err) {
        console.error('Failed to load data:', err);
        return null;
    }
}
```

### SQL

```sql
-- Find top 10 customers by order total
SELECT
    c.customer_id,
    c.first_name,
    c.last_name,
    COUNT(o.order_id)   AS total_orders,
    SUM(o.total_amount) AS revenue
FROM customers c
INNER JOIN orders o ON o.customer_id = c.customer_id
WHERE o.status = 'completed'
GROUP BY c.customer_id, c.first_name, c.last_name
ORDER BY revenue DESC
LIMIT 10;
```

### Bash / Shell

```bash
#!/usr/bin/env bash
set -euo pipefail

# Deploy script
APP_DIR="/var/www/myapp"
BACKUP_DIR="/var/backups/myapp"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

echo "Creating backup..."
cp -r "$APP_DIR" "$BACKUP_DIR/$TIMESTAMP"

echo "Pulling latest code..."
cd "$APP_DIR"
git pull origin main

echo "Restarting service..."
systemctl restart myapp

echo "Done. Backup stored at $BACKUP_DIR/$TIMESTAMP"
```

### Python

```python
from dataclasses import dataclass, field
from typing import List

@dataclass
class Student:
    name: str
    grades: List[float] = field(default_factory=list)

    def average(self) -> float:
        if not self.grades:
            return 0.0
        return sum(self.grades) / len(self.grades)

students = [
    Student("Alice", [90, 85, 92]),
    Student("Bob",   [78, 81, 88]),
]

for s in students:
    print(f"{s.name}: {s.average():.1f}")
```

### HTML

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Example</title>
</head>
<body>
    <h1 class="title">Hello, World!</h1>
    <!-- This is a comment -->
    <p>A simple HTML page.</p>
</body>
</html>
```

### JSON

```json
{
    "name": "md2html-php",
    "version": "1.0.0",
    "license": "MIT",
    "scripts": {
        "test": "phpunit tests"
    },
    "enabled": true,
    "count": 42
}
```

---

## Tables

| Language   | Extension | Highlighting |
|:-----------|:---------:|-------------:|
| PHP        | `.php`    | ✅           |
| JavaScript | `.js`     | ✅           |
| TypeScript | `.ts`     | ✅           |
| SQL        | `.sql`    | ✅           |
| Bash       | `.sh`     | ✅           |
| Python     | `.py`     | ✅           |
| HTML/XML   | `.html`   | ✅           |
| CSS        | `.css`    | ✅           |
| JSON       | `.json`   | ✅           |

---

## Blockquotes

> "Any fool can write code that a computer can understand. Good programmers
> write code that humans can understand."
>
> — Martin Fowler

---

## Lists

### Unordered

- Item one
- Item two
  - Nested item A
  - Nested item B
- Item three

### Ordered

1. First step
2. Second step
3. Third step
   1. Sub-step 3.1
   2. Sub-step 3.2

---

*Generated with [md2html-php](https://github.com/SilverDay/md2html-php)*
