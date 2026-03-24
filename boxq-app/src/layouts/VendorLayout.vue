<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const currentUser = ref({ name: '', email: '', role: '' });
const isNavOpen = ref(false);

onMounted(() => {
    const userData = localStorage.getItem('user');
    if (userData) {
        currentUser.value = JSON.parse(userData);
    }
});

const handleLogout = async () => {
    await authStore.logout();
    router.push('/login');
};

const toggleNav = () => {
    isNavOpen.value = !isNavOpen.value;
};
</script>

<script lang="ts"> export default { name: 'VendorLayout' } </script>

<template>
    <div class="min-vh-100 bg-light d-flex flex-column smooth-layout">
        <nav class="navbar navbar-expand-lg navbar-dark sticky-top shadow-sm" style="background-color: #1e1e2d; transition: all 0.3s ease;">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="#">
                    <div class="bg-primary rounded p-2 d-flex align-items-center justify-content-center">
                        <i class="fa-solid fa-handshake text-white fs-5"></i>
                    </div>
                    BoxQ <span class="fw-light text-white-50">| Supplier Portal</span>
                </a>
                
                <button class="navbar-toggler border-0 shadow-none" type="button" @click="toggleNav">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" :class="{ 'show': isNavOpen }">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0 mt-3 mt-lg-0">
                        <li class="nav-item">
                            <router-link to="/vendor/dashboard" class="nav-link px-3 rounded" :class="{ 'active bg-white bg-opacity-10': route.path === '/vendor/dashboard' }">
                                <i class="fa-solid fa-file-invoice me-2"></i>My Orders
                            </router-link>
                        </li>
                    </ul>
                    <div class="d-flex align-items-center gap-3 mt-3 mt-lg-0 border-top border-lg-0 border-secondary border-opacity-25 pt-3 pt-lg-0">
                        <div class="text-white text-end d-none d-lg-block">
                            <div class="fw-bold small lh-1">{{ currentUser.name }}</div>
                            <div class="text-white-50" style="font-size: 0.75rem;">{{ currentUser.email }}</div>
                        </div>
                        <button @click="handleLogout" class="btn btn-outline-light btn-sm fw-bold w-100 w-lg-auto">
                            <i class="fa-solid fa-right-from-bracket me-2"></i>Sign Out
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <main class="flex-grow-1 py-4 py-md-5 container">
            <slot></slot>
        </main>
        
        <footer class="bg-white border-top py-4 mt-auto">
            <div class="container text-center text-muted small">
                &copy; 2026 BoxQ Procurement Systems. All rights reserved.
            </div>
        </footer>
    </div>
</template>

<style scoped>
.smooth-layout {
    transition: all 0.3s ease-in-out;
}
.nav-link {
    transition: all 0.2s ease;
}
.nav-link:hover {
    background-color: rgba(255,255,255,0.05);
}
</style>