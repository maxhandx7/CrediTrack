import React, { useState } from 'react';
import { Form, Button, Container, Alert, Card } from 'react-bootstrap';
import { useNavigate } from 'react-router-dom';
import { login } from '../service/api';

const LoginClient = () => {
  const [document, setDocument] = useState('');
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const handleLogin = async (e) => {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      const response = await login({ document }, 'client');
      const { data } = response;

      localStorage.setItem('token', data.token);
      localStorage.setItem('client', JSON.stringify(data.client));

      navigate('/dashboard-client'); // Redirige al dashboard del cliente
    } catch (err) {
      console.error('Error en login de cliente:', err);
      setError(err.response?.data?.message || 'Documento no válido o no registrado');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Container className="d-flex justify-content-center align-items-center vh-100">
      <Card className="p-4 shadow-lg" style={{ width: '400px' }}>
        <h3 className="text-center mb-4">Acceso de Clientes</h3>
        {error && <Alert variant="danger">{error}</Alert>}

        <Form onSubmit={handleLogin}>
          <Form.Group className="mb-3">
            <Form.Label>Cédula</Form.Label>
            <Form.Control
              type="text"
              placeholder="Ingresa tu número de cédula"
              value={document}
              onChange={(e) => setDocument(e.target.value)}
              required
            />
          </Form.Group>

          <Button
            variant="primary"
            type="submit"
            className="w-100"
            disabled={loading}
          >
            {loading ? 'Verificando...' : 'Ingresar'}
          </Button>
        </Form>
      </Card>
    </Container>
  );
};

export default LoginClient;
