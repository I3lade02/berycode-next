"use client";

import emailjs from "@emailjs/browser";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";

import Input from "@/components/ui/Input";
import Textarea from "@/components/ui/Textarea";
import {
  contactSchema,
  type ContactFormValues,
} from "@/lib/validators/contact";

const serviceId = process.env.NEXT_PUBLIC_EMAILJS_SERVICE_ID;
const templateId = process.env.NEXT_PUBLIC_EMAILJS_TEMPLATE_ID;
const publicKey = process.env.NEXT_PUBLIC_EMAILJS_PUBLIC_KEY;

export default function ContactForm() {
  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting, isSubmitSuccessful },
  } = useForm<ContactFormValues>({
    resolver: zodResolver(contactSchema),
    defaultValues: {
      name: "",
      email: "",
      message: "",
    },
  });

  async function onSubmit(values: ContactFormValues) {
    if (!serviceId || !templateId || !publicKey) {
      setError("root", {
        message: "Missing config of EmailJS, please check .env.local .",
      });
      return;
    }

    try {
      await emailjs.send(
        serviceId,
        templateId,
        {
          name: values.name,
          email: values.email,
          message: values.message,
        },
        {
          publicKey,
        },
      );

      reset();
    } catch (error) {
      console.error("EmailJS error:", error);
      setError("root", {
        message: "Unable to send message, please try again.",
      });
    }
  }

  return (
    <form
      onSubmit={handleSubmit(onSubmit)}
      className="glass-card soft-shadow max-w-2xl space-y-6 rounded-[1.75rem] p-6"
      noValidate
    >
      <div className="space-y-2">
        <label
          htmlFor="name"
          className="text-sm font-medium text-zinc-800 dark:text-zinc-200"
        >
          Name
        </label>
        <Input id="name" placeholder="Your name" {...register("name")} />
        {errors.name ? (
          <p className="text-sm text-red-600">{errors.name.message}</p>
        ) : null}
      </div>

      <div className="space-y-2">
        <label
          htmlFor="email"
          className="text-sm font-medium text-zinc-800 dark:text-zinc-200"
        >
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
        <label
          htmlFor="message"
          className="text-sm font-medium text-zinc-800 dark:text-zinc-200"
        >
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
        className="inline-flex rounded-xl border border-zinc-900 bg-zinc-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
      >
        {isSubmitting ? "Sending..." : "Send message"}
      </button>

      {errors.root ? (
        <p className="text-sm text-red-600">{errors.root.message}</p>
      ) : null}

      {isSubmitSuccessful ? (
        <p className="text-sm text-green-600">Message successfully submitted</p>
      ) : null}
    </form>
  );
}
