<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../services/api';
import MainLayout from '../../layouts/MainLayout.vue';

interface Product {
    _id?: string;
    id?: string;
    name: string;
    sku: string;
    description: string;
    category: string;
    price: number;
    is_active: boolean;
}

const router = useRouter();
const products = ref<Product[]>([]);
const loading = ref(true);
const showForm = ref(false);
const isEditing = ref(false);
const currentEditId = ref<string | null>(null);

const form = reactive({
    name: '',
    sku: '',
    description: '',
    category: '',
    price: 0,
    is_active: true
});

onMounted(async () => {
    const userData = localStorage.getItem('user');
    if (userData) {
        const user = JSON.parse(userData);
        if (user.role !== 'admin') {
            router.push('/');
            return;
        }
    } else {
        router.push('/login');
        return;
    }
    await fetchProducts();
});

const fetchProducts = async () => {
    loading.value = true;
    try {
        const response = await api.get('/products');
        products.value = response.data;
    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
};

const openAddForm = () => {
    resetForm();
    isEditing.value = false;
    showForm.value = true;
};

const openEditForm = (product: Product) => {
    currentEditId.value = product._id || product.id || null;
    form.name = product.name;
    form.sku = product.sku;
    form.description = product.description;
    form.category = product.category;
    form.price = product.price;
    form.is_active = product.is_active;
    isEditing.value = true;
    showForm.value = true;
};

const resetForm = () => {
    currentEditId.value = null;
    form.name = '';
    form.sku = '';
    form.description = '';
    form.category = '';
    form.price = 0;
    form.is_active = true;
    showForm.value = false;
};

const submitForm = async () => {
    try {
        if (isEditing.value && currentEditId.value) {
            await api.put(`/products/${currentEditId.value}`, form);
        } else {
            await api.post('/products', form);
        }
        await fetchProducts();
        resetForm();
    } catch (error) {
        alert("Failed to save product. Please check your data and ensure the SKU is unique.");
    }
};

const deleteProduct = async (id: string | undefined) => {
    if (!id) return;
    if (!confirm("Are you sure you want to permanently delete this product?")) return;
    
    try {
        await api.delete(`/products/${id}`);
        await fetchProducts();
    } catch (error) {
        alert("Failed to delete product.");
    }
};
</script>

<script lang="ts">
export default {
    name: 'ProductManagementView'
}
</script>

<template>
    <MainLayout>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Product Management</h3>
                <p class="text-muted mb-0">Admin controls for the purchasing catalog.</p>
            </div>
            <button v-if="!showForm" @click="openAddForm" class="btn btn-dark shadow-sm">
                <i class="fa-solid fa-plus me-2"></i>Add Product
            </button>
        </div>

        <div v-if="showForm" class="card border-0 shadow-sm p-4 mb-4">
            <h5 class="fw-bold mb-4">{{ isEditing ? 'Edit Product' : 'Add New Product' }}</h5>
            <form @submit.prevent="submitForm">
                <div class="row mb-3">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label class="form-label small fw-bold text-secondary">Product Name</label>
                        <input v-model="form.name" type="text" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">SKU Code</label>
                        <input v-model="form.sku" type="text" class="form-control" required>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label class="form-label small fw-bold text-secondary">Category</label>
                        <input v-model="form.category" type="text" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">Price ($)</label>
                        <input v-model.number="form.price" type="number" step="0.01" min="0" class="form-control" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-bold text-secondary">Description</label>
                    <textarea v-model="form.description" class="form-control" rows="2"></textarea>
                </div>
                <div class="mb-4 form-check form-switch">
                    <input v-model="form.is_active" class="form-check-input" type="checkbox" id="activeSwitch">
                    <label class="form-check-label small fw-bold text-secondary" for="activeSwitch">Item is Active and Purchasable</label>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4">Save Product</button>
                    <button type="button" @click="resetForm" class="btn btn-outline-secondary px-4">Cancel</button>
                </div>
            </form>
        </div>

        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-secondary" role="status">
                <span class="visually-hidden"></span>
            </div>
        </div>

        <div v-else-if="!showForm" class="card border-0 shadow-sm">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 text-uppercase small fw-bold text-secondary">SKU</th>
                            <th class="text-uppercase small fw-bold text-secondary">Name</th>
                            <th class="text-uppercase small fw-bold text-secondary">Category</th>
                            <th class="text-uppercase small fw-bold text-secondary">Price</th>
                            <th class="text-uppercase small fw-bold text-secondary">Status</th>
                            <th class="text-uppercase small fw-bold text-secondary text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="product in products" :key="product._id || product.id">
                            <td class="ps-4 font-monospace small text-muted">{{ product.sku }}</td>
                            <td class="fw-bold text-dark">{{ product.name }}</td>
                            <td><span class="badge bg-light text-dark border">{{ product.category }}</span></td>
                            <td class="fw-bold">${{ Number(product.price).toFixed(2) }}</td>
                            <td>
                                <span v-if="product.is_active" class="badge bg-success bg-opacity-75">Active</span>
                                <span v-else class="badge bg-danger bg-opacity-75">Inactive</span>
                            </td>
                            <td class="text-end pe-4">
                                <button @click="openEditForm(product)" class="btn btn-sm btn-outline-primary me-2">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button @click="deleteProduct(product._id || product.id)" class="btn btn-sm btn-outline-danger">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </MainLayout>
</template>