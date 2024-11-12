'use client'

import axios from '@/lib/axios'
import useSWR, { mutate } from 'swr'
import { useState } from 'react'
import Button from '@/components/Button'
import Input from '@/components/Input'
import InputError from '@/components/InputError'
import Label from '@/components/Label'

export const CreateGroup = () => {

    const { data: groups } = useSWR("/api/groups");

    const categories = Array.isArray(groups) ? groups : groups?.categories || [];

    const [isOpen, setIsOpen] = useState(false);
    const toggleCollapse = () => {
        setIsOpen(!isOpen);
    };

    const csrf = () => axios.get("/sanctum/csrf-cookie")

    const grabData = async ({ setErrors, setResponse, ...props }) => {

        await csrf()

        setErrors([])

        axios
            .post("/api/create-groups", props)
            .then(response => {
                setResponse(response.data)
                mutate("/api/groups");
                setName("");
                setCategory("");
            })
            .catch(error => {
                if (error.response.status !== 422) throw error
                setErrors(error.response.data.errors)
            })
    }

    const [name, setName] = useState("")
    const [selectedCategory, setCategory] = useState("");
    const [response, setResponse] = useState(null)
    const [errors, setErrors] = useState([])

    const submitForm = event => {
        event.preventDefault()

        grabData({
            name,
            selectedCategory,
            setResponse,
            setErrors,
        })
    }

    return (
        <form id="createGroupForm" onSubmit={submitForm}>
            <div className="mt-5">
                <div className="w-full max-w-md">
                    <button type="button" className="w-full bg-blue-500 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                        onClick={toggleCollapse}>
                        Crea un gruppo
                    </button>
                </div>
            </div>
            <div className={`mt-2 overflow-hidden transition-all duration-300 ease-in-out ${isOpen ? 'max-h-screen' : 'max-h-0'}`}>

                {/* Name */}
                <div>
                    <Label htmlFor="name">Name</Label>

                    <Input
                        id="name"
                        type="text"
                        value={name}
                        className="block mt-3"
                        onChange={event => setName(event.target.value)}
                        required
                        autoFocus
                    />

                    <InputError messages={errors.name} className="mt-2" />
                </div>

                {/* Categories */}
                <div className="d-flex mt-4">
                    <Label htmlFor="category">Categoria</Label>

                    <select
                        id="category"
                        name="category"
                        className="block mt-3"
                        value={selectedCategory} // valore selezionato
                        onChange={event => setCategory(event.target.value)} // handler per il cambio di valore
                        required
                        autoFocus
                    >
                        <option value="">Seleziona una categoria</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </select>

                    <InputError messages={errors.category} className="mt-2" />
                </div>

                <div className="flex items-center justify-end mt-4">
                    <Button className="ml-4">Crea</Button>
                </div>

                {response?.message && (
                    <div className="mt-2 text-green italic">{response.message}</div>
                )}
                {errors.length > 0 && (
                    <div className="mt-2">
                        {errors.map((error, index) => (
                            <div key={index}>{error.message}</div>
                        ))}
                    </div>
                )}
            </div>
        </form>
    )
}

export default CreateGroup