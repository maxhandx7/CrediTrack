import { useEffect, useState } from "react";
import { Calendar as BigCalendar, momentLocalizer } from "react-big-calendar";
import { getSchedules, updateSchedule } from "../service/api";
import { Modal, Button, Form } from "react-bootstrap";
import { parseISO } from "date-fns";
import axios from "axios";
import "react-big-calendar/lib/css/react-big-calendar.css";
import { Calendar as CalendarIcon } from "lucide-react";
import moment from "moment";
import "moment/locale/es";

const localizer = momentLocalizer(moment);
moment.locale("es");

const Calendar = () => {
  const [schedules, setSchedules] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false); // 👈 nuevo
  const [selectedEvent, setSelectedEvent] = useState(null); // 👈 nuevo
  const [form, setForm] = useState({
    loan_id: "",
    scheduled_date: "",
    amount_due: "",
  });
  const [editForm, setEditForm] = useState({ // 👈 nuevo
    id: "",
    scheduled_date: "",
  });

  useEffect(() => {
    fetchSchedules();
  }, []);

  const fetchSchedules = async () => {
    try {
      const res = await getSchedules();
      const events = res.data.map((item) => ({
        id: item.id,
        title: `${item.loan?.client?.name || "Cliente"} - $${item.amount_due}`,
        start: parseISO(item.scheduled_date),
        end: parseISO(item.scheduled_date),
        status: item.status,
      }));
      setSchedules(events);
    } catch (error) {
      console.error("Error al obtener los cronogramas:", error);
    } finally {
      setLoading(false);
    }
  };

  const handleCreate = async (e) => {
    e.preventDefault();
    try {
      await axios.post("/api/schedules", form);
      setShowModal(false);
      fetchSchedules();
    } catch (error) {
      console.error("Error al crear cuota:", error);
      alert("Hubo un problema al crear la cuota");
    }
  };

  // 👇 Nuevo: al hacer clic en un evento del calendario
  const handleSelectEvent = (event) => {
    setSelectedEvent(event);

    setEditForm({
      id: event.id,
      client_name: event.client_name || event.title.split(" - ")[0],
      amount_due: event.amount_due || event.title.split("$")[1]?.trim(),
      scheduled_date: moment(event.start).format("YYYY-MM-DD"),
      status: event.status || "pendiente",
      notes: event.notes || "",
    });
    setShowEditModal(true);
  };

  // 👇 Nuevo: actualizar la fecha del pago
  const handleUpdate = async (e) => {
    e.preventDefault();
    try {
      await updateSchedule(editForm.id, {
        scheduled_date: editForm.scheduled_date,
      });
      setShowEditModal(false);
      fetchSchedules();
    } catch (error) {
      console.error("Error al actualizar la fecha:", error);
      alert("Hubo un problema al actualizar la fecha del pago");
    }
  };

  const eventStyleGetter = (event) => {
    let backgroundColor = "#007bff";
    if (event.status === "pagado") backgroundColor = "#28a745";
    if (event.status === "atrasado") backgroundColor = "#dc3545";
    if (event.status === "pendiente") backgroundColor = "#ffc107";

    return {
      style: {
        backgroundColor,
        borderRadius: "10px",
        color: "white",
        border: "none",
        display: "block",
        padding: "5px",
        cursor: "pointer",
      },
    };
  };

  return (
    <div className="container mt-4">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <h3 className="fw-bold d-flex align-items-center gap-2">
          <CalendarIcon /> Cronograma de Pagos
        </h3>
        <Button variant="primary" onClick={() => setShowModal(true)}>
          + Agregar Cuota
        </Button>
      </div>

      {loading ? (
        <div className="text-center py-4">
          <div className="spinner-border text-primary"></div>
          <p className="mt-2">Cargando calendario...</p>
        </div>
      ) : (
        <div className="bg-white p-3 rounded shadow-sm">
          <BigCalendar
            localizer={localizer}
            events={schedules}
            startAccessor="start"
            endAccessor="end"
            style={{ height: 600 }}
            eventPropGetter={eventStyleGetter}
            onSelectEvent={handleSelectEvent} // 👈 aquí el click
            messages={{
              today: "Hoy",
              previous: "Atrás",
              next: "Siguiente",
              month: "Mes",
              week: "Semana",
              day: "Día",
              agenda: "Agenda",
              date: "Fecha",
              time: "Hora",
              event: "Evento",
              showMore: (total) => `+ Ver ${total} más`,
            }}
          />
        </div>
      )}

      {/* Modal para crear nueva cuota */}
      <Modal show={showModal} onHide={() => setShowModal(false)}>
        <Modal.Header closeButton>
          <Modal.Title>Agregar Cuota</Modal.Title>
        </Modal.Header>
        <Form onSubmit={handleCreate}>
          <Modal.Body>
            <Form.Group className="mb-3">
              <Form.Label>ID del Préstamo</Form.Label>
              <Form.Control
                type="number"
                value={form.loan_id}
                onChange={(e) => setForm({ ...form, loan_id: e.target.value })}
                required
              />
            </Form.Group>
            <Form.Group className="mb-3">
              <Form.Label>Fecha Programada</Form.Label>
              <Form.Control
                type="date"
                value={form.scheduled_date}
                onChange={(e) =>
                  setForm({ ...form, scheduled_date: e.target.value })
                }
                required
              />
            </Form.Group>
            <Form.Group className="mb-3">
              <Form.Label>Monto</Form.Label>
              <Form.Control
                type="number"
                value={form.amount_due}
                onChange={(e) =>
                  setForm({ ...form, amount_due: e.target.value })
                }
                required
              />
            </Form.Group>
          </Modal.Body>
          <Modal.Footer>
            <Button variant="secondary" onClick={() => setShowModal(false)}>
              Cancelar
            </Button>
            <Button variant="primary" type="submit">
              Guardar
            </Button>
          </Modal.Footer>
        </Form>
      </Modal>

      {/* 👇 Modal: Editar información del pago */}
      <Modal show={showEditModal} onHide={() => setShowEditModal(false)} centered>
        <Modal.Header closeButton>
          <Modal.Title>Editar Pago Programado</Modal.Title>
        </Modal.Header>
        <Form onSubmit={handleUpdate}>
          <Modal.Body>
            <div className="mb-3 p-3 bg-light rounded border">
              <h6 className="fw-bold text-secondary mb-2">📋 Detalles del Pago</h6>
              <p className="mb-1">
                <strong>Cliente:</strong> {editForm.client_name || "—"}
              </p>
              <p className="mb-1">
                <strong>Monto:</strong>{" "}
                {editForm.amount_due
                  ? `$${Number(editForm.amount_due).toLocaleString("es-CO")}`
                  : "—"}
              </p>
              <p className="mb-0">
                <strong>Estado:</strong>{" "}
                <span
                  className={`badge ${editForm.status === "pagado"
                      ? "bg-success"
                      : editForm.status === "pendiente"
                        ? "bg-warning text-dark"
                        : editForm.status === "atrasado"
                          ? "bg-danger"
                          : "bg-secondary"
                    }`}
                >
                  {editForm.status || "Desconocido"}
                </span>
              </p>
            </div>

            <Form.Group className="mb-3">
              <Form.Label className="fw-semibold">Nueva Fecha Programada</Form.Label>
              <Form.Control
                type="date"
                value={editForm.scheduled_date}
                onChange={(e) =>
                  setEditForm({ ...editForm, scheduled_date: e.target.value })
                }
                required
              />
            </Form.Group>

            <Form.Group className="mb-3">
              <Form.Label>Notas (opcional)</Form.Label>
              <Form.Control
                as="textarea"
                rows={2}
                placeholder="Motivo del cambio o comentario..."
                value={editForm.notes || ""}
                onChange={(e) =>
                  setEditForm({ ...editForm, notes: e.target.value })
                }
              />
            </Form.Group>
          </Modal.Body>
          <Modal.Footer>
            <Button variant="secondary" onClick={() => setShowEditModal(false)}>
              Cancelar
            </Button>
            <Button variant="success" type="submit">
              Actualizar
            </Button>
          </Modal.Footer>
        </Form>
      </Modal>

    </div>
  );
};

export default Calendar;
