import './globals.css'
import type { Metadata } from 'next'
import { Inter } from 'next/font/google'
import { NextUIProvider } from '@nextui-org/react';
import * as React from "react";

const inter = Inter({ subsets: ['latin'] })

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
        <body className={inter.className}>{children}</body>
      </html>
  )
}
