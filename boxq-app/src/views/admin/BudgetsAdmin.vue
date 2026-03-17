<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import MainLayout from '../../layouts/MainLayout.vue';

interface Budget {
    department: string;
    monthly_limit: number;
}

const budgets = ref<Budget[]>([]);
const loading = ref(true);
const saving = ref(false);
const showSuccess = ref(false);

const departments = ['Engineering', 'HR', 'Finance', 'IT', 'Marketing', 'Sales', 'Operations'];

const fetchBudgets = async () => {
    try {
        const response = await api.get('/budgets');
        const existing = response.data;
        
        budgets.value = departments.map(dept => {
            const found = existing.find((b: Budget) => b.department === dept);
            return { 
                department: dept, 
                monthly_limit: found ? found.monthly_limit : 0 
            };
        });
    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
};

const saveBudgets = async () => {
    saving.value = true;
    showSuccess.value = false;
    try {
        for (const budget of budgets.value) {
            await api.post('/budgets', { 
                department: budget.department, 
                monthly_limit: budget.monthly_limit 
            });
        }
        showSuccess.value = true;
        setTimeout(() => showSuccess.value = false, 3000);
    } catch (error) {
        alert("Failed to update budgets.");
    } finally {
        saving.value = false;
    }
};

onMounted(() => {
    fetchBudgets();
});
</script>

<script lang="ts">
export default {
    name: 'BudgetsAdmin'
}
</script>

<template>
    <MainLayout>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Budget Allocation</h3>
                <p class="text-muted mb-0">Manage monthly spend limits per department (USD).</p>
            </div>
            <button @click="saveBudgets" class="btn btn-dark shadow-sm px-4 fw-bold" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
                <i v-else class="fa-solid fa-floppy-disk me-2"></i>
                {{ saving ? 'Saving...' : 'Save All Budgets' }}
            </button>
        </div>

        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-secondary" role="status"></div>
        </div>

        <div v-else class="card border-0 shadow-sm rounded-3">
            <div v-if="showSuccess" class="alert alert-success m-3 py-2 border-0 fw-bold">
                <i class="fa-solid fa-circle-check me-2"></i>Budgets updated successfully.
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 text-uppercase small fw-bold text-secondary py-3 w-50">Department</th>
                            <th class="text-uppercase small fw-bold text-secondary py-3 pe-4 text-end">Monthly Limit (USD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(budget, index) in budgets" :key="index">
                            <td class="ps-4 fw-bold text-dark">{{ budget.department }}</td>
                            <td class="pe-4 text-end">
                                <div class="input-group justify-content-end">
                                    <span class="input-group-text bg-light border-end-0 text-muted">$</span>
                                    <input v-model.number="budget.monthly_limit" type="number" min="0" class="form-control text-end border-start-0" style="max-width: 150px;">
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.form-control:focus {
    box-shadow: none;
    border-color: #dee2e6;
}
</style>