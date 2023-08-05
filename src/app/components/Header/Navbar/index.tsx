'use client'

import Image from "next/image";
import React from "react";
import {Navbar, NavbarBrand, NavbarContent, NavbarItem, Link, Button} from "@nextui-org/react";

export default function NavBar() {
  return (
    <header>
      <Navbar position="sticky">
      <NavbarBrand>
        <Image
          src="/logo.png"
          alt="Logo do Atelie Tricotar"
          height={50}
          width={50}
        />
      </NavbarBrand>
      <NavbarContent className="hidden sm:flex gap-4" justify="center">
        <NavbarItem>
          <Link className="text-pink-500" color="foreground" href="#">
            Galeria
          </Link>
        </NavbarItem>
        <NavbarItem isActive>
          <Link className="text-pink-500" href="#" aria-current="page">
            Contato
          </Link>
        </NavbarItem>
        <NavbarItem>
          <Link className="text-pink-500" color="foreground" href="#">
            Sobre nós
          </Link>
        </NavbarItem>
      </NavbarContent>
      <NavbarContent justify="end">
      </NavbarContent>
    </Navbar>
    </header>
  );
}
