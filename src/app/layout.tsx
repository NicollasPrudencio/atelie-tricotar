import './globals.css'
import type { Metadata } from 'next'
import { Roboto } from 'next/font/google'
import { NextUIProvider } from '@nextui-org/react';
import * as React from "react";

const roboto = Roboto({weight: "700", subsets: ['latin']})

export const metadata: Metadata = {
  title: 'Ateliê Tricotar - Site',
  description: 'Os melhores presentes artesanais.',
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
      <html className='bg-pink-200' lang="pt-br">
        <body className={roboto.className}>{children}</body>
      </html>
  )
}
