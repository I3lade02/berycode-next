"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { useState } from "react";

import Input from "@/components/ui/Input";
import Textarea from "@/components/ui/Textarea";
import { 
    contactSchema,
    type ContactFormValues,
} from "@/lib/validators/contact";

export default function ContactForm() {
    const [isSubmittingSuccessful, setIsSubmittingSuccessful] = useState(false);
    
    const {
        register,
        handleSubmit,
        reset,
        formState: { errors, isSubmitting },
    } = useForm<ContactFormValues>({
        resolver: zodResolver(contactSchema),
        defaultValues: {
            name: "",
            email: "",
            message: "",
        },
    });

    async function onSubmit(values: ContactFormValues) {
        await new Promise((resolve) => setTimeout(resolve, 800));
        console.log("Contact form submitted:", values);
        setIsSubmittingSuccessful(true);
        reset();
        setTimeout(() => setIsSubmittingSuccessful(false), 3000);
    }

    return (
        <form
            onSubmit={handleSubmit(onSubmit)}
            className="max-w-2xl space-y-6 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm"
            noValidate
        >
            <div className="space-y-2">
                <label htmlFor="name" className="text-sm font-medium text-zinc-800">
                    Name
                </label>
                <Input id="name" placeholder="Your name" {...register("name")} />
                {errors.name ? (
                    <p className="text-sm text-red-600">{errors.name.message}</p>
                ) : null}
            </div>

            <div className="space-y-2">
                <label htmlFor="email" className="text-sm font-medium text-zinc-800">
                    Email
                </label>
                <Input
                    id="email"
                    type="email"
                    placeholder="your@email.com"
                    {...register("email")}
                />
                {errors.email ? (
                    <p className="text-sm text-red-600">{errors.email.message}</p>
                ) : null}
            </div>

            <div className="space-y-2">
                <label htmlFor="message" className="text-sm font-medium text-zinc-800">
                    Message
                </label>
                <Textarea
                    id="message"
                    placeholder="Tell me a bit about your project or idea..."
                    {...register("message")}
                />
                {errors.message ? (
                    <p className="text-sm text-red-600">{errors.message.message}</p>
                ) : null}
            </div>

            <button
                type="submit"
                disabled={isSubmitting}
                className="inline-flex rounded-xl bg-zinc-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled: opacity-60"
            >
                {isSubmitting ? "Sending..." : "Send message"}
            </button>

            {isSubmittingSuccessful ? (
                <p className="text-sm text-green-600">
                    Message submitted successfully. For now it only logs to the console.
                </p>
            ) : null}
        </form>
    );
}