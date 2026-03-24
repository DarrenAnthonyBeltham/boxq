import { createRouter, createWebHistory } from 'vue-router';

const Login = () => import('../views/auth/Login.vue');
const Register = () => import('../views/auth/Register.vue');
const RequisitionDashboard = () => import('../views/procurement/RequisitionDashboard.vue');
const CreateRequest = () => import('../views/procurement/RequisitionCreate.vue');
const RequisitionDetail = () => import('../views/procurement/RequisitionDetail.vue');
const Profile = () => import('../views/settings/Profile.vue');
const ProductList = () => import('../views/catalog/ProductList.vue');
const ProductManagement = () => import('../views/admin/ProductManagement.vue');
const AccountSettings = () => import('../views/procurement/AccountSettings.vue');
const VendorDashboard = () => import('../views/vendor/VendorDashboard.vue');

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { 
      path: '/login', 
      name: 'login', 
      component: Login,
      meta: { guest: true }
    },
    { 
      path: '/register', 
      name: 'register', 
      component: Register,
      meta: { guest: true }
    },
    { 
      path: '/', 
      name: 'dashboard', 
      component: RequisitionDashboard,
      meta: { requiresAuth: true }
    },
    { 
      path: '/create', 
      name: 'create', 
      component: CreateRequest,
      meta: { requiresAuth: true }
    },
    { 
      path: '/requisition/:id', 
      name: 'requisition-detail', 
      component: RequisitionDetail,
      meta: { requiresAuth: true }
    },
    { 
      path: '/profile', 
      name: 'profile', 
      component: Profile,
      meta: { requiresAuth: true }
    },
    {
      path: '/catalog',
      name: 'catalog',
      component: ProductList,
      meta: { requiresAuth: true }
    },
    {
      path: '/admin/products',
      name: 'admin-products',
      component: ProductManagement,
      meta: { requiresAuth: true }
    },
    {
      path: '/settings',
      name: 'settings',
      component: AccountSettings,
      meta: { requiresAuth: true }
    },
    {
      path: '/vendors',
      name: 'vendors',
      component: () => import('../views/procurement/VendorDirectory.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/purchase-orders',
      name: 'purchase-orders',
      component: () => import('../views/procurement/PurchaseOrders.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/receipts',
      name: 'receipts',
      component: () => import('../views/procurement/GoodsReceipt.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/admin/budgets',
      name: 'admin-budgets',
      component: () => import('../views/admin/BudgetsAdmin.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/admin/users',
      name: 'user-management',
      component: () => import('../views/admin/UserManagementView.vue'),
      meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
      path: '/analytics',
      name: 'analytics',
      component: () => import('../views/analytics/AnalyticsDashboardView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/vendor/dashboard',
      name: 'vendor-dashboard',
      component: VendorDashboard,
      meta: { requiresAuth: true, roles: ['vendor'] }
    }
  ]
});

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token');
  const userStr = localStorage.getItem('user');
  let userRole = '';
  
  if (userStr) {
    try {
      const user = JSON.parse(userStr);
      userRole = user.role || '';
    } catch (e) {}
  }

  if (to.meta.requiresAuth && !token) {
    next('/login');
  } else if (to.meta.guest && token) {
    if (userRole === 'vendor') {
        next('/vendor/dashboard');
    } else {
        next('/');
    }
  } else if (token) {
    if (userRole === 'vendor' && !to.path.startsWith('/vendor')) {
        next('/vendor/dashboard');
    } else if (userRole !== 'vendor' && to.path.startsWith('/vendor')) {
        next('/');
    } else {
        next();
    }
  } else {
    next();
  }
});

export default router;