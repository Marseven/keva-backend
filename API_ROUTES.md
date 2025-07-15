# KEVA API Routes Documentation

## Overview
This document provides a comprehensive list of all API routes available in the KEVA e-commerce platform.

## Base URL
- **Development**: `http://localhost:8000/api`
- **Production**: `https://api.keva.ga`

## Authentication
- **Public Routes**: No authentication required
- **Protected Routes**: Require `Bearer {token}` header (Laravel Sanctum)
- **Admin Routes**: Require admin privileges

---

## 🔐 Authentication Routes

### Public Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/register` | Register new user |
| POST | `/auth/login` | User login |

### Protected Authentication
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| POST | `/auth/logout` | Logout current session | `sanctum` |
| POST | `/auth/logout-all` | Logout all sessions | `sanctum` |

---

## 👤 User Profile Routes

### Profile Management
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/profile` | Get user profile | `sanctum, active_user` |
| PUT | `/profile` | Update user profile | `sanctum, active_user` |
| GET | `/user/profile` | Get detailed user profile | `sanctum, active_user` |
| PUT | `/user/profile` | Update detailed user profile | `sanctum, active_user` |
| POST | `/user/avatar` | Upload user avatar | `sanctum, active_user` |
| PUT | `/user/password` | Change password | `sanctum, active_user` |
| GET | `/user/dashboard` | Get user dashboard | `sanctum, active_user` |
| DELETE | `/user/account` | Delete user account | `sanctum, active_user` |

### Token Management
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/user/tokens` | Get user tokens | `sanctum, active_user` |
| DELETE | `/user/tokens/{tokenId}` | Revoke specific token | `sanctum, active_user` |

---

## 📦 Product Routes

### Public Product Access
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/products` | List all products |
| GET | `/products/search` | Search products |
| GET | `/products/{product}` | Get product details |

### Product Management (Protected)
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/my-products` | Get user's products | `sanctum, active_user, subscription` |
| POST | `/products` | Create new product | `sanctum, active_user, subscription` |
| PUT | `/products/{product}` | Update product | `sanctum, active_user, subscription` |
| DELETE | `/products/{product}` | Delete product | `sanctum, active_user, subscription` |
| POST | `/products/{product}/duplicate` | Duplicate product | `sanctum, active_user, subscription` |
| PUT | `/products/{product}/stock` | Update stock | `sanctum, active_user, subscription` |
| POST | `/products/{product}/toggle-featured` | Toggle featured status | `sanctum, active_user, subscription` |

---

## 🛒 Cart Routes

### Cart Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/cart` | Get cart contents |
| POST | `/cart` | Add item to cart |
| PUT | `/cart/{cart}` | Update cart item |
| DELETE | `/cart/{cart}` | Remove cart item |
| GET | `/cart/count` | Get cart items count |
| POST | `/cart/validate-cart` | Validate cart |
| DELETE | `/cart/clear` | Clear entire cart |

---

## 📋 Order Routes

### Order Management (Protected)
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/orders` | List user orders | `sanctum, active_user, subscription` |
| POST | `/orders` | Create new order | `sanctum, active_user, subscription` |
| GET | `/orders/{order}` | Get order details | `sanctum, active_user, subscription` |
| PUT | `/orders/{order}/cancel` | Cancel order | `sanctum, active_user, subscription` |
| PUT | `/orders/{order}/status` | Update order status | `sanctum, active_user, subscription` |
| PUT | `/orders/{order}/notes` | Add order notes | `sanctum, active_user, subscription` |
| GET | `/orders/{order}/track` | Track order | `sanctum, active_user, subscription` |
| POST | `/orders/{order}/reorder` | Reorder items | `sanctum, active_user, subscription` |

### Order Statistics
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/orders/stats` | Order statistics | `sanctum, active_user, subscription` |
| GET | `/orders/sales` | Sales data | `sanctum, active_user, subscription` |
| GET | `/orders/sales/stats` | Sales statistics | `sanctum, active_user, subscription` |

---

## 💳 Payment Routes

### Payment Management (Protected)
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/payments` | List user payments | `sanctum, active_user` |
| POST | `/payments` | Create payment | `sanctum, active_user` |
| GET | `/payments/{payment}` | Get payment details | `sanctum, active_user` |
| PUT | `/payments/{payment}/cancel` | Cancel payment | `sanctum, active_user` |

### Payment Webhooks
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/payments/webhook` | Payment webhook |

---

## 💰 Subscription Routes

### Subscription Management (Protected)
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/subscriptions` | List user subscriptions | `sanctum, active_user` |
| POST | `/subscriptions` | Create subscription | `sanctum, active_user` |
| GET | `/subscriptions/current` | Get current subscription | `sanctum, active_user` |
| PUT | `/subscriptions/{subscription}/cancel` | Cancel subscription | `sanctum, active_user` |
| PUT | `/subscriptions/{subscription}/resume` | Resume subscription | `sanctum, active_user` |

### Subscription Webhooks
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/subscriptions/webhook` | Subscription webhook |

---

## 📄 Invoice Routes

### Invoice Management (Protected)
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/invoices` | List user invoices | `sanctum, active_user` |
| GET | `/invoices/{invoice}` | Get invoice details | `sanctum, active_user` |
| GET | `/invoices/{invoice}/download` | Download invoice PDF | `sanctum, active_user` |
| POST | `/invoices/{invoice}/send` | Send invoice by email | `sanctum, active_user` |

---

## 📊 Plan Routes

### Public Plan Access
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/plans` | List all plans |
| GET | `/plans/compare` | Compare plans |
| GET | `/plans/{slug}` | Get plan details |

---

## 🏷️ Category Routes

### Public Category Access
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/categories` | List all categories |
| GET | `/categories/tree` | Get category tree |
| GET | `/categories/{slug}` | Get category details |
| GET | `/categories/{slug}/products` | Get category products |

---

## 🔧 Admin Routes

### Admin Dashboard
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/admin/dashboard` | Admin dashboard | `sanctum, admin` |
| GET | `/admin/dashboard/stats` | Dashboard statistics | `sanctum, admin` |
| GET | `/admin/dashboard/analytics` | Analytics data | `sanctum, admin` |
| GET | `/admin/dashboard/recent-activity` | Recent activity | `sanctum, admin` |

### Admin User Management
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/admin/users` | List all users | `sanctum, admin` |
| POST | `/admin/users` | Create user | `sanctum, admin` |
| GET | `/admin/users/{user}` | Get user details | `sanctum, admin` |
| PUT | `/admin/users/{user}` | Update user | `sanctum, admin` |
| DELETE | `/admin/users/{user}` | Delete user | `sanctum, admin` |
| PATCH | `/admin/users/{user}/toggle-status` | Toggle user status | `sanctum, admin` |
| PUT | `/admin/users/{user}/reset-password` | Reset password | `sanctum, admin` |
| GET | `/admin/users/statistics` | User statistics | `sanctum, admin` |

### Admin Product Management
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/admin/products` | List all products | `sanctum, admin` |
| POST | `/admin/products` | Create product | `sanctum, admin` |
| GET | `/admin/products/{product}` | Get product details | `sanctum, admin` |
| PUT | `/admin/products/{product}` | Update product | `sanctum, admin` |
| DELETE | `/admin/products/{product}` | Delete product | `sanctum, admin` |
| PATCH | `/admin/products/{product}/toggle-featured` | Toggle featured | `sanctum, admin` |
| PATCH | `/admin/products/{product}/publish` | Publish product | `sanctum, admin` |
| PATCH | `/admin/products/{product}/unpublish` | Unpublish product | `sanctum, admin` |
| PUT | `/admin/products/{product}/stock` | Update stock | `sanctum, admin` |
| GET | `/admin/products/statistics` | Product statistics | `sanctum, admin` |
| POST | `/admin/products/bulk-action` | Bulk actions | `sanctum, admin` |

### Admin Order Management
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/admin/orders` | List all orders | `sanctum, admin` |
| GET | `/admin/orders/{order}` | Get order details | `sanctum, admin` |
| PUT | `/admin/orders/{order}/status` | Update order status | `sanctum, admin` |
| PUT | `/admin/orders/{order}/cancel` | Cancel order | `sanctum, admin` |
| GET | `/admin/orders/statistics` | Order statistics | `sanctum, admin` |

### Admin Payment Management
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/admin/payments` | List all payments | `sanctum, admin` |
| GET | `/admin/payments/{payment}` | Get payment details | `sanctum, admin` |
| PUT | `/admin/payments/{payment}/refund` | Refund payment | `sanctum, admin` |
| GET | `/admin/payments/statistics` | Payment statistics | `sanctum, admin` |

### Admin Subscription Management
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/admin/subscriptions` | List all subscriptions | `sanctum, admin` |
| GET | `/admin/subscriptions/{subscription}` | Get subscription details | `sanctum, admin` |
| PUT | `/admin/subscriptions/{subscription}/cancel` | Cancel subscription | `sanctum, admin` |
| GET | `/admin/subscriptions/statistics` | Subscription statistics | `sanctum, admin` |

### Admin Invoice Management
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/admin/invoices` | List all invoices | `sanctum, admin` |
| GET | `/admin/invoices/{invoice}` | Get invoice details | `sanctum, admin` |
| POST | `/admin/invoices/{invoice}/regenerate` | Regenerate invoice | `sanctum, admin` |
| GET | `/admin/invoices/statistics` | Invoice statistics | `sanctum, admin` |

### Admin Plan Management
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/admin/plans` | List all plans | `sanctum, admin` |
| POST | `/admin/plans` | Create plan | `sanctum, admin` |
| GET | `/admin/plans/{plan}` | Get plan details | `sanctum, admin` |
| PUT | `/admin/plans/{plan}` | Update plan | `sanctum, admin` |
| DELETE | `/admin/plans/{plan}` | Delete plan | `sanctum, admin` |
| PATCH | `/admin/plans/{plan}/toggle-active` | Toggle plan status | `sanctum, admin` |

### Admin Category Management
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/admin/categories` | List all categories | `sanctum, admin` |
| POST | `/admin/categories` | Create category | `sanctum, admin` |
| GET | `/admin/categories/{category}` | Get category details | `sanctum, admin` |
| PUT | `/admin/categories/{category}` | Update category | `sanctum, admin` |
| DELETE | `/admin/categories/{category}` | Delete category | `sanctum, admin` |
| PATCH | `/admin/categories/{category}/toggle-active` | Toggle category status | `sanctum, admin` |

---

## 🌐 Utility Routes

### Public Utilities
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/health` | API health check |
| GET | `/business-types` | List business types |
| GET | `/cities` | List cities |

---

## 🔗 Webhook Routes

### External Webhooks
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/ebilling/callback` | EBILLING payment callback |
| POST | `/payments/webhook` | Payment webhook |
| POST | `/subscriptions/webhook` | Subscription webhook |

---

## 📖 Documentation Routes

### API Documentation
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/documentation` | Swagger UI documentation |
| GET | `/oauth2-callback` | OAuth2 callback for API testing |

---

## 🛡️ Middleware Legend

- **sanctum**: Laravel Sanctum authentication required
- **active_user**: User must be active (not suspended)
- **subscription**: User must have active subscription
- **admin**: Admin privileges required

---

## 📊 Route Statistics

- **Total Routes**: 120+
- **Public Routes**: 15
- **Protected Routes**: 65
- **Admin Routes**: 40
- **Webhook Routes**: 3

---

## 🔍 Response Format

All API responses follow this standard format:

```json
{
  "success": true,
  "message": "Success message",
  "data": {
    // Response data
  },
  "errors": null
}
```

For paginated responses:
```json
{
  "success": true,
  "data": {
    "items": [...],
    "pagination": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 15,
      "total": 73,
      "from": 1,
      "to": 15
    }
  }
}
```

---

## 🚀 Getting Started

1. **Authentication**: Start with `/auth/register` or `/auth/login`
2. **Authorization**: Use the returned token in `Authorization: Bearer {token}` header
3. **API Documentation**: Visit `/api/documentation` for interactive API docs
4. **Testing**: Use the provided examples and the Swagger UI

---

*Last updated: $(date)*
*API Version: 1.0.0*