<script setup lang="ts">
import { ref, onMounted, computed, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

interface ToastMessage {
    id: number;
    title: string;
    message: string;
    time: string;
}

interface RequisitionEvent {
    requisition: {
        requester: string;
        department: string;
        total_price: number | string;
    }
}

const router = useRouter();
const userStr = localStorage.getItem('user');
const currentUser = ref(userStr ? JSON.parse(userStr) : { name: 'User', department: 'Dept', role: 'Role', avatar: null });

const toasts = ref<ToastMessage[]>([]);
let cleanupEcho: (() => void) | null = null;

const currentDate = computed(() => {
    const today = new Date();
    return today.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
});

const logout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    router.push('/login');
};

const removeToast = (id: number) => {
    toasts.value = toasts.value.filter(t => t.id !== id);
};

onMounted(() => {
    Object.assign(window, { Pusher });

    const echo = new Echo({
        broadcaster: 'pusher',
        key: 'c1a52c4d254fa5cd0f95',
        cluster: 'ap1',
        forceTLS: true
    });

    echo.channel('requisitions')
        .listen('.requisition.created', (e: RequisitionEvent) => {
            if (e.requisition.department === currentUser.value.department || currentUser.value.role === 'admin') {
                const toastId = Date.now();
                toasts.value.push({
                    id: toastId,
                    title: 'New Requisition',
                    message: `${e.requisition.requester} submitted a new request for $${e.requisition.total_price}.`,
                    time: new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})
                });

                setTimeout(() => {
                    removeToast(toastId);
                }, 5000);
            }
        });

    cleanupEcho = () => {
        echo.leaveChannel('requisitions');
    };

    window.addEventListener('user-updated', () => {
        const updatedStr = localStorage.getItem('user');
        if (updatedStr) {
            currentUser.value = JSON.parse(updatedStr);
        }
    });
});

onUnmounted(() => {
    if (cleanupEcho) {
        cleanupEcho();
    }
});
</script>

<template>
    <div class="d-flex vh-100 overflow-hidden" style="background-color: #f8fafc;">
        <div class="d-flex flex-column flex-shrink-0 p-3" style="width: 260px; background-color: #1e2329;">
            <div class="d-flex align-items-center mb-4 mt-2 px-3 text-white text-decoration-none">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="#3b82f6" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
                <span class="fs-4 fw-bold ms-3">BoxQ</span>
            </div>
            
            <hr class="border-secondary opacity-25 mx-2">
            
            <ul class="nav nav-pills flex-column mb-auto gap-2 mt-2">
                <li class="nav-item">
                    <router-link to="/" class="nav-link text-secondary d-flex align-items-center gap-3 px-3 py-2 rounded-3" active-class="bg-primary text-white fw-bold">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        Dashboard
                    </router-link>
                </li>
                <li class="nav-item">
                    <router-link to="/catalog" class="nav-link text-secondary d-flex align-items-center gap-3 px-3 py-2 rounded-3" active-class="bg-primary text-white fw-bold">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                        Catalog
                    </router-link>
                </li>
                <li class="nav-item">
                    <router-link to="/create" class="nav-link text-secondary d-flex align-items-center gap-3 px-3 py-2 rounded-3" active-class="bg-primary text-white fw-bold">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        New Request
                    </router-link>
                </li>
                <li class="nav-item" v-if="currentUser.role === 'admin' || currentUser.role === 'manager' || currentUser.role === 'finance'">
                    <router-link to="/vendors" class="nav-link text-secondary d-flex align-items-center gap-3 px-3 py-2 rounded-3" active-class="bg-primary text-white fw-bold">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        Vendors
                    </router-link>
                </li>
                <li class="nav-item" v-if="currentUser.role === 'admin' || currentUser.role === 'manager' || currentUser.role === 'finance'">
                    <router-link to="/purchase-orders" class="nav-link text-secondary d-flex align-items-center gap-3 px-3 py-2 rounded-3" active-class="bg-primary text-white fw-bold">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        Purchase Orders
                    </router-link>
                </li>
                <li class="nav-item">
                    <router-link to="/receipts" class="nav-link text-secondary d-flex align-items-center gap-3 px-3 py-2 rounded-3" active-class="bg-primary text-white fw-bold">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                        Receiving
                    </router-link>
                </li>
                <li class="nav-item" v-if="currentUser.role === 'admin'">
                    <router-link to="/admin/budgets" class="nav-link text-secondary d-flex align-items-center gap-3 px-3 py-2 rounded-3" active-class="bg-primary text-white fw-bold">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>
                        Budgets
                    </router-link>
                </li>
                <li class="nav-item" v-if="currentUser.role === 'admin'">
                    <router-link to="/admin/products" class="nav-link text-secondary d-flex align-items-center gap-3 px-3 py-2 rounded-3" active-class="bg-primary text-white fw-bold">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                        Manage Products
                    </router-link>
                </li>
            </ul>
            
            <hr class="border-secondary opacity-25 mx-2 mb-3">
            
            <router-link to="/settings" class="d-flex align-items-center px-3 py-2 mb-2 text-decoration-none rounded-3 profile-link transition-all">
                <div class="rounded-circle bg-secondary d-flex justify-content-center align-items-center text-white fw-bold me-3 flex-shrink-0" style="width: 42px; height: 42px; font-size: 1.2rem;">
                    {{ currentUser.name.charAt(0).toUpperCase() }}
                </div>
                <div class="text-white overflow-hidden">
                    <strong class="d-block text-truncate">{{ currentUser.name }}</strong>
                    <small class="text-secondary text-truncate d-block">{{ currentUser.department }} &bull; {{ currentUser.role }}</small>
                </div>
            </router-link>
        </div>

        <div class="flex-grow-1 d-flex flex-column overflow-auto position-relative">
            <header class="d-flex justify-content-between align-items-center p-3 bg-white border-bottom px-4">
                <div class="text-muted fw-medium d-flex align-items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    Today is {{ currentDate }}
                </div>
                <button @click="logout" class="btn btn-outline-danger btn-sm px-3 py-2 fw-bold d-flex align-items-center gap-2 rounded-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    Logout
                </button>
            </header>

            <div class="p-4 p-md-5">
                <slot />
            </div>

            <div class="toast-container position-fixed bottom-0 end-0 p-4" style="z-index: 1055;">
                <div v-for="toast in toasts" :key="toast.id" class="toast show bg-white shadow-lg border-0 rounded-3 mb-3" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header bg-primary text-white border-0 rounded-top-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        <strong class="me-auto">{{ toast.title }}</strong>
                        <small>{{ toast.time }}</small>
                        <button type="button" class="btn-close btn-close-white ms-2" @click="removeToast(toast.id)" aria-label="Close"></button>
                    </div>
                    <div class="toast-body fw-medium text-dark">
                        {{ toast.message }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.nav-link.text-secondary:hover {
    color: #ffffff !important;
}

.profile-link {
    cursor: pointer;
}

.profile-link:hover {
    background-color: rgba(255, 255, 255, 0.05);
}

.toast {
    animation: slideInRight 0.3s ease-out forwards;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
</style>