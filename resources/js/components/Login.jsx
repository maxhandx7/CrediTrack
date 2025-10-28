import React, { useState } from "react";
import { login } from "../services/api.jsx";
import { useNavigate } from "react-router-dom";
import { Container, Form, Button } from "react-bootstrap";

export default function Login() {
  const [type, setType] = useState("user");
  const [form, setForm] = useState({});
  const navigate = useNavigate();

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      const res = await login(form, type);
      localStorage.setItem("token", res.data.token);
      localStorage.setItem("userType", type);

      if (type === "user") navigate("/dashboard");
      else navigate("/client");
    } catch (err) {
      alert("Credenciales inválidas");
    }
  };

  return (
    <Container className="d-flex flex-column justify-content-center vh-100" style={{ maxWidth: 400 }}>
      <h3 className="text-center mb-4">CrediTrack</h3>
      <Form onSubmit={handleSubmit}>
        <Form.Group className="mb-3">
          <Form.Label>Tipo de acceso</Form.Label>
          <Form.Select value={type} onChange={(e) => setType(e.target.value)}>
            <option value="user">Prestamista</option>
            <option value="client">Cliente</option>
          </Form.Select>
        </Form.Group>

        {type === "user" ? (
          <>
            <Form.Group className="mb-3">
              <Form.Label>Correo</Form.Label>
              <Form.Control name="email" onChange={handleChange} required />
            </Form.Group>
            <Form.Group className="mb-3">
              <Form.Label>Contraseña</Form.Label>
              <Form.Control name="password" type="password" onChange={handleChange} required />
            </Form.Group>
          </>
        ) : (
          <Form.Group className="mb-3">
            <Form.Label>Cédula</Form.Label>
            <Form.Control name="document" onChange={handleChange} required />
          </Form.Group>
        )}

        <Button type="submit" className="w-100 btn-primary">
          Ingresar
        </Button>
      </Form>
    </Container>
  );
}
