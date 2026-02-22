<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import api from '../../services/api';
import MainLayout from '../../layouts/MainLayout.vue';

const currentUser = ref({
    name: '',
    email: '',
    department: '',
    role: '',
    avatar: '',
    preferences: {
        email_on_status: true,
        email_on_new: false
    }
});

const passwordForm = reactive({
    current_password: '',
    new_password: '',
    new_password_confirmation: ''
});

const prefForm = reactive({
    email_on_status: true,
    email_on_new: false
});

const isSubmittingPassword = ref(false);
const isSavingPrefs = ref(false);
const isUploading = ref(false);
const passSuccess = ref('');
const passError = ref('');
const prefSuccess = ref('');
const fileInput = ref<HTMLInputElement | null>(null);

onMounted(() => {
    const userData = localStorage.getItem('user');
    if (userData) {
        const parsed = JSON.parse(userData);
        currentUser.value = parsed;
        if (parsed.preferences) {
            prefForm.email_on_status = parsed.preferences.email_on_status;
            prefForm.email_on_new = parsed.preferences.email_on_new;
        }
    }
});

const updatePassword = async () => {
    isSubmittingPassword.value = true;
    passSuccess.value = '';
    passError.value = '';

    try {
        await api.put('/user/password', passwordForm);
        passSuccess.value = 'Your password has been successfully updated.';
        passwordForm.current_password = '';
        passwordForm.new_password = '';
        passwordForm.new_password_confirmation = '';
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            passError.value = error.response.data.errors?.current_password?.[0] 
                || error.response.data.errors?.new_password?.[0] 
                || 'Invalid input provided.';
        } else {
            passError.value = 'A server error occurred. Please try again later.';
        }
    } finally {
        isSubmittingPassword.value = false;
    }
};

const savePreferences = async () => {
    isSavingPrefs.value = true;
    prefSuccess.value = '';

    try {
        const response = await api.put('/user/preferences', { preferences: prefForm });
        prefSuccess.value = 'Preferences saved successfully.';
        
        currentUser.value = response.data.user;
        localStorage.setItem('user', JSON.stringify(response.data.user));
        
        setTimeout(() => prefSuccess.value = '', 3000);
    } catch (error) {
        alert("Failed to save preferences.");
    } finally {
        isSavingPrefs.value = false;
    }
};

const triggerFileUpload = () => {
    fileInput.value?.click();
};

const handleFileUpload = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (!target.files || target.files.length === 0) return;

    const file = target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('avatar', file);

    isUploading.value = true;
    try {
        const response = await api.post('/user/avatar', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        
        currentUser.value = response.data.user;
        localStorage.setItem('user', JSON.stringify(response.data.user));
        
        window.dispatchEvent(new Event('user-updated'));
        
    } catch (error) {
        alert("Failed to upload image. Must be a jpeg/png under 2MB.");
    } finally {
        isUploading.value = false;
        if (fileInput.value) fileInput.value.value = ''; 
    }
};
</script>

<script lang="ts">
export default {
    name: 'ProfileView'
}
</script>

<template>
    <MainLayout>
        <div class="mb-4">
            <h3 class="fw-bold text-dark mb-1">Account Settings</h3>
            <p class="text-muted mb-0">Manage your profile and security preferences.</p>
        </div>

        <div class="row">
            <div class="col-lg-4 col-md-5 mb-4 mb-md-0">
                <div class="card border-0 shadow-sm text-center p-4">
                    <div class="position-relative d-inline-block mx-auto mb-3" @click="triggerFileUpload" style="cursor: pointer;">
                        <input type="file" class="d-none" ref="fileInput" accept="image/*" @change="handleFileUpload">
                        <img v-if="currentUser.avatar" :src="`http://127.0.0.1:8000${currentUser.avatar}`" class="rounded-circle object-fit-cover shadow-sm" style="width: 120px; height: 120px; border: 4px solid white;">
                        <div v-else class="bg-secondary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 120px; height: 120px; border: 4px solid white;">
                            <span class="fs-1 fw-bold text-secondary">
                                {{ currentUser.name ? currentUser.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() : '' }}
                            </span>
                        </div>
                        <div class="position-absolute bottom-0 end-0 bg-dark text-white rounded-circle d-flex align-items-center justify-content-center border border-white" style="width: 35px; height: 35px; right: 5px !important; bottom: 5px !important;">
                            <span v-if="isUploading" class="spinner-border spinner-border-sm" role="status"></span>
                            <i v-else class="fa-solid fa-camera small"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-1">{{ currentUser.name }}</h5>
                    <p class="text-muted small mb-3">{{ currentUser.email }}</p>
                    <div class="d-flex justify-content-center gap-2">
                        <span class="badge bg-light text-dark border">{{ currentUser.department }}</span>
                        <span class="badge bg-dark text-capitalize">{{ currentUser.role }}</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-8 col-md-7">
                <div class="card border-0 shadow-sm p-4 mb-4">
                    <h6 class="text-uppercase text-muted small fw-bold mb-4">Change Password</h6>

                    <div v-if="passSuccess" class="alert alert-success py-2 small">
                        <i class="fa-solid fa-circle-check me-2"></i>{{ passSuccess }}
                    </div>
                    <div v-if="passError" class="alert alert-danger py-2 small">
                        <i class="fa-solid fa-circle-exclamation me-2"></i>{{ passError }}
                    </div>

                    <form @submit.prevent="updatePassword">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Current Password</label>
                            <input v-model="passwordForm.current_password" type="password" class="form-control" required>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="form-label small fw-bold text-secondary">New Password</label>
                                <input v-model="passwordForm.new_password" type="password" class="form-control" minlength="8" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Confirm New Password</label>
                                <input v-model="passwordForm.new_password_confirmation" type="password" class="form-control" minlength="8" required>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-dark px-4" :disabled="isSubmittingPassword || !passwordForm.current_password || !passwordForm.new_password">
                                <span v-if="isSubmittingPassword" class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>

                <div class="card border-0 shadow-sm p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="text-uppercase text-muted small fw-bold mb-0">Notification Preferences</h6>
                        <span v-if="prefSuccess" class="text-success small fw-bold">
                            <i class="fa-solid fa-check me-1"></i> Saved
                        </span>
                    </div>

                    <div class="list-group list-group-flush mb-4 border-bottom border-top">
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3 px-0">
                            <div>
                                <h6 class="mb-1 fw-bold text-dark">Status Updates</h6>
                                <p class="mb-0 small text-muted">Email me when my requests are approved, rejected, or paid.</p>
                            </div>
                            <div class="form-check form-switch fs-4">
                                <input v-model="prefForm.email_on_status" class="form-check-input" type="checkbox" role="switch">
                            </div>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3 px-0 border-bottom-0">
                            <div>
                                <h6 class="mb-1 fw-bold text-dark">New Requisitions</h6>
                                <p class="mb-0 small text-muted">Email me when a new request requires my attention.</p>
                            </div>
                            <div class="form-check form-switch fs-4">
                                <input v-model="prefForm.email_on_new" class="form-check-input" type="checkbox" role="switch">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button @click="savePreferences" class="btn btn-outline-dark px-4" :disabled="isSavingPrefs">
                            <span v-if="isSavingPrefs" class="spinner-border spinner-border-sm me-2" role="status"></span>
                            Save Preferences
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>