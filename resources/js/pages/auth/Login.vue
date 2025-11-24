<script setup lang="ts">
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/login';
import { Form, Head } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const showTermsDialog = ref(false);

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <AuthBase
        description="Enter your email and password below to log in"
    >
        <Head title="Log in" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Remember me</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <LoaderCircle
                        v-if="processing"
                        class="h-4 w-4 animate-spin"
                    />
                    Log in
                </Button>

                <div class="text-center text-xs text-muted-foreground">
                    By logging in, you agree to our
                    <button
                        type="button"
                        @click="showTermsDialog = true"
                        class="text-primary underline hover:text-primary/80 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 rounded px-0.5"
                    >
                        Terms and Conditions
                    </button>
                </div>
            </div>

        </Form>

        <!-- Terms and Conditions Dialog -->
        <AlertDialog v-model:open="showTermsDialog">
            <AlertDialogContent class="max-w-md">
                <AlertDialogHeader>
                    <AlertDialogTitle>Terms and Conditions</AlertDialogTitle>
                    <AlertDialogDescription class="text-left space-y-3 pt-2">
                        <p class="font-semibold text-foreground">By accessing and using HRNexus, you agree to the following terms:</p>
                        
                        <div class="space-y-2 text-sm">
                            <p><strong>1. Account Responsibility:</strong> You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account.</p>
                            
                            <p><strong>2. Acceptable Use:</strong> You agree to use the system only for legitimate business purposes and in accordance with company policies. Unauthorized access, data manipulation, or misuse is strictly prohibited.</p>
                            
                            <p><strong>3. Data Privacy:</strong> Your personal and professional data will be handled in accordance with our privacy policy. Sensitive information is protected and access is restricted to authorized personnel only.</p>
                            
                            <p><strong>4. System Access:</strong> Access to HRNexus is granted based on your role and department. Sharing credentials or attempting to access unauthorized areas is prohibited.</p>
                            
                            <p><strong>5. Compliance:</strong> You must comply with all applicable laws, regulations, and company policies while using this system.</p>
                        </div>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Close</AlertDialogCancel>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AuthBase>
</template>
