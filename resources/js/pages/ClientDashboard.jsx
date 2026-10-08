import React, { useEffect, useState } from 'react';
import { Container, Card, Table, Badge, Button, Spinner, Alert, ProgressBar } from 'react-bootstrap';
import { useNavigate } from 'react-router-dom';
import { getClientPortalLoans } from '../service/api';

const money = (v) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(v || 0);
const date = (d) => new Date(`${d}T12:00:00`).toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' });
const STATUS = { pagado: 'success', pendiente: 'secondary', vencido: 'danger', activo: 'primary', atrasado: 'danger', cancelado: 'dark' };

/** Portal del deudor: solo ve sus propios préstamos. */
const ClientDashboard = () => {
  const [loans, setLoans] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const navigate = useNavigate();
  const client = JSON.parse(localStorage.getItem('client') || 'null');

  useEffect(() => {
    getClientPortalLoans()
      .then(({ data }) => setLoans(data))
      .catch((err) => {
        if ([401, 403].includes(err.response?.status)) return logout();
        setError('No se pudo cargar tu información.');
      })
      .finally(() => setLoading(false));
  }, []);

  const logout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('client');
    navigate('/login-client');
  };

  if (loading) return <Container className="py-5 text-center"><Spinner animation="border" /></Container>;

  return (
    <Container className="py-4" style={{ maxWidth: 900 }}>
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h3 className="mb-0">Hola, {client?.name?.split(' ')[0]}</h3>
        <Button variant="outline-secondary" size="sm" onClick={logout}>Salir</Button>
      </div>
      {error && <Alert variant="danger">{error}</Alert>}
      {loans.length === 0 && !error && <Alert variant="info">No tienes préstamos registrados.</Alert>}

      {loans.map((loan) => {
        const next = loan.schedules.find((s) => s.amount_pending > 0);
        const progress = loan.total_amount > 0 ? Math.round((loan.total_paid / loan.total_amount) * 100) : 0;
        return (
          <Card key={loan.id} className="mb-4 shadow-sm">
            <Card.Body>
              <div className="d-flex justify-content-between flex-wrap gap-2 mb-3">
                <div>
                  <div className="text-muted small">Préstamo con {loan.user?.name}</div>
                  <h4 className="mb-0">{money(loan.balance)} <small className="text-muted fs-6">por pagar</small></h4>
                </div>
                <Badge bg={STATUS[loan.status]} className="align-self-start text-capitalize">{loan.status}</Badge>
              </div>
              <ProgressBar now={progress} label={`${progress}%`} className="mb-3" />
              {next && (
                <Alert variant={next.status === 'vencido' ? 'danger' : 'light'} className="py-2">
                  Próxima cuota: <strong>{money(next.amount_pending)}</strong> el {date(next.scheduled_date)}
                  {next.status === 'vencido' && ' (vencida)'}
                </Alert>
              )}
              <Table size="sm" responsive className="mb-0">
                <thead><tr><th>Fecha</th><th className="text-end">Cuota</th><th className="text-end">Abonado</th><th>Estado</th></tr></thead>
                <tbody>
                  {loan.schedules.map((s) => (
                    <tr key={s.id}>
                      <td>{date(s.scheduled_date)}</td>
                      <td className="text-end">{money(s.amount_due)}</td>
                      <td className="text-end">{money(s.amount_paid)}</td>
                      <td><Badge bg={STATUS[s.status]} className="text-capitalize">{s.status}</Badge></td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            </Card.Body>
          </Card>
        );
      })}
    </Container>
  );
};

export default ClientDashboard;
