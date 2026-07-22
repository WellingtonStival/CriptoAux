import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { CheckCircle2, XCircle } from 'lucide-react';
import { verifyEmail } from '../services/api';
import { useAuth } from '../context/AuthContext';
import AuthLayout from '../components/AuthLayout';

function VerifyEmail() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { login } = useAuth();

  const token = searchParams.get('token') ?? '';
  const email = searchParams.get('email') ?? '';

  const [status, setStatus] = useState('verifying'); // verifying | success | error

  // Evita disparar a verificação duas vezes: o Strict Mode do React (só
  // em desenvolvimento) monta/desmonta/remonta o componente de propósito
  // pra pegar efeitos colaterais mal escritos, e o token só pode ser
  // usado uma vez - uma segunda chamada falharia mesmo a primeira tendo
  // funcionado. Importante: não soma isso com um flag de "componente
  // ainda montado" pra ignorar a resposta - o Strict Mode roda a limpeza
  // do primeiro efeito antes da resposta chegar, então a única
  // requisição que de fato disparou teria sua resposta descartada e a
  // tela ficaria travada em "verificando" pra sempre. O React 18+ já
  // ignora com segurança um setState de um componente desmontado de
  // verdade, então não precisamos desse controle aqui.
  const requestedTokenRef = useRef(null);

  useEffect(() => {
    if (!token || !email) {
      setStatus('error');
      return;
    }

    if (requestedTokenRef.current === token) {
      return;
    }
    requestedTokenRef.current = token;

    verifyEmail({ token, email })
      .then((response) => {
        setStatus('success');
        login(response.data.token);
        setTimeout(() => navigate('/'), 1500);
      })
      .catch(() => {
        setStatus('error');
      });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token, email]);

  if (status === 'verifying') {
    return (
      <AuthLayout title="Confirmando email">
        <p className="text-sm text-muted-foreground">Só um instante...</p>
      </AuthLayout>
    );
  }

  if (status === 'success') {
    return (
      <AuthLayout title="Email confirmado">
        <div className="flex flex-col items-start gap-3">
          <CheckCircle2 className="size-6 text-success" />
          <p className="text-sm text-muted-foreground">
            Sua conta foi confirmada. Redirecionando...
          </p>
        </div>
      </AuthLayout>
    );
  }

  return (
    <AuthLayout title="Link inválido">
      <div className="flex flex-col items-start gap-3">
        <XCircle className="size-6 text-destructive" />
        <p className="text-sm text-muted-foreground">
          Esse link de confirmação é inválido ou já expirou. Faça login pra
          solicitar um novo.
        </p>
      </div>

      <p className="mt-4 text-sm text-muted-foreground">
        <Link to="/login" className="text-primary hover:underline">
          ← Voltar para o login
        </Link>
      </p>
    </AuthLayout>
  );
}

export default VerifyEmail;
