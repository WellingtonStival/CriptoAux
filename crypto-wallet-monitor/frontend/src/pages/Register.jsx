import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { UserPlus } from 'lucide-react';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';
import AuthLayout from '../components/AuthLayout';
import { Input } from '../components/ui/input';
import { Button } from '../components/ui/button';
import { Alert, AlertDescription } from '../components/ui/alert';

function Register() {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const { login } = useAuth();
  const navigate = useNavigate();

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');

    if (password !== passwordConfirmation) {
      setError('As senhas não coincidem.');
      return;
    }

    setSaving(true);

    try {
      const response = await api.post('/register', {
        name,
        email,
        password,
      });

      login(response.data.token);

      // navega para a home protegida sem recarregar a página
      navigate('/');
    } catch (err) {
      const validationErrors = err.response?.data?.errors;
      const firstError = validationErrors
        ? Object.values(validationErrors)[0]?.[0]
        : null;

      setError(firstError ?? 'Não foi possível criar a conta. Tente novamente.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <AuthLayout title="Criar conta">
      {error && (
        <Alert variant="destructive" className="mb-4">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      <form onSubmit={handleSubmit} className="flex flex-col gap-3">
        <Input
          type="text"
          placeholder="Nome"
          value={name}
          onChange={e => setName(e.target.value)}
          disabled={saving}
          required
        />

        <Input
          type="email"
          placeholder="Email"
          value={email}
          onChange={e => setEmail(e.target.value)}
          disabled={saving}
          required
        />

        <Input
          type="password"
          placeholder="Senha"
          value={password}
          onChange={e => setPassword(e.target.value)}
          disabled={saving}
          required
        />

        <Input
          type="password"
          placeholder="Confirmar senha"
          value={passwordConfirmation}
          onChange={e => setPasswordConfirmation(e.target.value)}
          disabled={saving}
          required
        />

        <Button type="submit" disabled={saving} className="mt-2">
          <UserPlus className="size-4" />
          {saving ? 'Criando conta...' : 'Criar conta'}
        </Button>
      </form>

      <p className="mt-4 text-sm text-muted-foreground">
        Já tem conta?{' '}
        <Link to="/login" className="text-primary hover:underline">
          Entrar
        </Link>
      </p>
    </AuthLayout>
  );
}

export default Register;
