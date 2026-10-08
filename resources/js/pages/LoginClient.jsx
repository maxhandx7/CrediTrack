import React, { useState } from 'react';
import { Form, Button, Container, Alert, Card } from 'react-bootstrap';
import { useNavigate } from 'react-router-dom';
import { requestClientCode, verifyClientCode } from '../service/api';

/** Paso 1: cédula. Paso 2: código de 6 dígitos que llega por WhatsApp. */
const LoginClient = () => {
  const [step, setStep] = useState('document');
  const [document, setDocument] = useState('');
  const [code, setCode] = useState('');
  const [info, setInfo] = useState(null);
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const askCode = async (e) => {
    e?.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const { data } = await requestClientCode(document);
      setInfo(data.message);
      setStep('code');
    } catch (err) {
      setError(err.response?.status === 429
        ? 'Pediste varios códigos seguidos. Espera unos minutos.'
        : err.response?.data?.message || 'No se pudo enviar el código.');
    } finally {
      setLoading(false);
    }
  };

  const verify = async (e) => {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const { data } = await verifyClientCode(document, code);
      localStorage.setItem('token', data.token);
      localStorage.setItem('client', JSON.stringify(data.client));
      navigate('/dashboard-client');
    } catch (err) {
      setError(err.response?.data?.message || 'Código incorrecto o vencido.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Container className="d-flex justify-content-center align-items-center vh-100">
      <Card className="p-4 shadow-lg" style={{ width: '400px' }}>
        <h3 className="text-center mb-2">Acceso de clientes</h3>
        <p className="text-center text-muted mb-4" style={{ fontSize: '.95rem' }}>
          {step === 'document' ? 'Consulta tus cuotas y pagos.' : `Revisa tu WhatsApp e ingresa el código.`}
        </p>
        {error && <Alert variant="danger">{error}</Alert>}
        {info && step === 'code' && <Alert variant="info">{info}</Alert>}

        {step === 'document' ? (
          <Form onSubmit={askCode}>
            <Form.Group className="mb-3">
              <Form.Label>Cédula</Form.Label>
              <Form.Control inputMode="numeric" autoComplete="off" placeholder="Tu número de cédula"
                value={document} onChange={(e) => setDocument(e.target.value)} required autoFocus />
            </Form.Group>
            <Button type="submit" className="w-100" disabled={loading}>
              {loading ? 'Enviando…' : 'Enviarme el código por WhatsApp'}
            </Button>
          </Form>
        ) : (
          <Form onSubmit={verify}>
            <Form.Group className="mb-3">
              <Form.Label>Código de 6 dígitos</Form.Label>
              <Form.Control inputMode="numeric" autoComplete="one-time-code" maxLength={6} placeholder="000000"
                value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))} required autoFocus
                style={{ letterSpacing: '.4em', fontSize: '1.4rem', textAlign: 'center' }} />
            </Form.Group>
            <Button type="submit" className="w-100 mb-2" disabled={loading || code.length !== 6}>
              {loading ? 'Verificando…' : 'Entrar'}
            </Button>
            <Button variant="link" className="w-100" onClick={() => { setStep('document'); setCode(''); setInfo(null); }}>
              Usar otra cédula o pedir un código nuevo
            </Button>
          </Form>
        )}
      </Card>
    </Container>
  );
};

export default LoginClient;
