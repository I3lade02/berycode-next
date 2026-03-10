import { z } from "zod";

export const contactSchema = z.object({
    name: z.string().min(2, "Name must have at least 2 characters"),
    email: z.email("Please enter a valid email"),
    message: z.string().min(10, "Message must have at least characters")
});

export type ContactFormValues = z.infer<typeof contactSchema>;