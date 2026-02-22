import { createRouter, createWebHistory } from 'vue-router';

const Login = () => import('../views/auth/Login.vue');
const Register = () => import('../views/auth/Register.vue');
const Dashboard = () => import('../views/procurement/Dashboard.vue');
const CreateRequest = () => import('../views/procurement/RequisitionCreate.vue');
const RequisitionDetail = () => import('../views/procurement/RequisitionDetail.vue');
const Profile = () => import('../views/settings/Profile.vue');

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
      component: Dashboard,
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
    }
  ]
});

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token');

  if (to.meta.requiresAuth && !token) {
    next('/login');
  } else if (to.meta.guest && token) {
    next('/');
  } else {
    next();
  }
});

export default router;