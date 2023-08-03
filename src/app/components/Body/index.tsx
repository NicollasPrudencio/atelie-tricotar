import Image from "next/image"

export default function Content(){
    return (
        <main className="h-full w-full mt-15 p-12">
            <div>
                <div className="flex justify-center">
                    <div>
                        <Image
                            src="/logo.png"
                            alt="Logo do Atelie Tricotar"
                            height={200}
                            width={200}
                        />
                    </div>
                    <div className="w-80 ml-20 flex flex-col items-center justify-center">
                        <h1 className="font-bold text-lg text-pink-400">Faça sua encomenda conosco!</h1>
                        <p className="mt-1">Solte sua criatividade! Nossa equipe está ansiosa para receber sua ideia ou modelo. Ah, sim, fazemos de tudo! Veja abaixo os diferentes tipos de artesanatos que temos.</p>
                    </div>
                    </div>
                </div>
        </main>
    )
}