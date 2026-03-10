<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import MainLayout from '../../layouts/MainLayout.vue';

interface Product {
    _id?: string;
    name: string;
    sku: string;
    description: string;
    category: string;
    price: number;
    is_active: boolean;
}

const products = ref<Product[]>([]);
const loading = ref(true);
const searchQuery = ref('');

const fetchProducts = async () => {
    try {
        const response = await api.get('/products');
        products.value = response.data;
    } catch (error) {
        console.error("Error fetching products:", error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchProducts();
});
</script>

<script lang="ts">
export default {
    name: 'ProductListView'
}
</script>

<template>
    <MainLayout>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Product Catalog</h3>
                <p class="text-muted mb-0">Browse approved items for requisition.</p>
            </div>
            <div class="w-25">
                <input v-model="searchQuery" type="text" class="form-control" placeholder="Search products...">
            </div>
        </div>

        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-secondary" role="status">
                <span class="visually-hidden"></span>
            </div>
        </div>

        <div v-else class="row row-cols-1 row-cols-md-3 g-4">
            <div v-for="product in products" :key="product._id" class="col">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-light text-secondary border">{{ product.category }}</span>
                            <span class="small font-monospace text-muted">{{ product.sku }}</span>
                        </div>
                        <h5 class="card-title fw-bold text-dark mb-2">{{ product.name }}</h5>
                        <p class="card-text text-muted small mb-4">{{ product.description }}</p>
                        <div class="mt-auto d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0">${{ Number(product.price).toFixed(2) }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>