import WalletItem from "./WalletItem";

function WalletList({ wallets, prices, onDeleted }) {
  return (
    <ul className="flex flex-col gap-3">
      {wallets.map(wallet => (
        <WalletItem
          key={wallet.id}
          wallet={wallet}
          prices={prices}
          onDeleted={onDeleted}
        />
      ))}
    </ul>
  );
}

export default WalletList;
